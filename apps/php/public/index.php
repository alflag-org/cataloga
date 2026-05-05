<?php

declare(strict_types=1);

use Cataloga\Audit\AuditLogger;
use Cataloga\Http\ApiController;
use Cataloga\Http\Request;
use Cataloga\Http\Response;
use Cataloga\Http\Router;
use Cataloga\Http\WebController;
use Cataloga\Mutation\ChangeService;
use Cataloga\Mutation\ChangeSessionRepository;
use Cataloga\Registry\DomainPackRepository;
use Cataloga\Registry\EntityRepository;
use Cataloga\Registry\PathGuard;
use Cataloga\Registry\RecordParser;
use Cataloga\Registry\RecordSerializer;
use Cataloga\Registry\RegistryFileRepository;
use Cataloga\Registry\RegistrySettingsRepository;
use Cataloga\Registry\RelationRepository;
use Cataloga\Registry\ResourceDependencyProjector;
use Cataloga\Registry\SchemaRepository;
use Cataloga\Validation\RegistryValidator;
use Cataloga\View\TemplateRenderer;

session_start();

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'Composer dependencies are missing. Run: composer install';
    exit(1);
}

require $autoload;
require dirname(__DIR__) . '/src/View/helpers.php';

$projectRoot = dirname(__DIR__, 3);
$registryRoot = getenv('CATALOGA_REGISTRY_PATH') ?: $projectRoot . '/registry';
$runtimeRoot = getenv('CATALOGA_RUNTIME_PATH') ?: $projectRoot . '/.cataloga';

if (!is_dir($registryRoot)) {
    http_response_code(500);
    echo 'Registry root not found: ' . h($registryRoot);
    exit(1);
}

if (!is_dir($runtimeRoot) && !mkdir($runtimeRoot, 0775, true) && !is_dir($runtimeRoot)) {
    http_response_code(500);
    echo 'Failed to create runtime directory: ' . h($runtimeRoot);
    exit(1);
}

$recordParser = new RecordParser();
$recordSerializer = new RecordSerializer();
$pathGuard = new PathGuard($registryRoot);
$registryFileRepository = new RegistryFileRepository($registryRoot);
$resourceDependencyProjector = new ResourceDependencyProjector();
$entityRepository = new EntityRepository($registryRoot, $recordParser, $recordSerializer, $pathGuard);
$relationRepository = new RelationRepository($registryRoot, $recordParser, $recordSerializer, $pathGuard, $resourceDependencyProjector);
$domainPackRepository = new DomainPackRepository($projectRoot, $recordParser);
$schemaRepository = new SchemaRepository($projectRoot, $registryRoot, $recordParser);
$settingsRepository = new RegistrySettingsRepository($registryRoot, $recordParser);
$changeRepository = new ChangeSessionRepository($runtimeRoot);
$validator = new RegistryValidator($schemaRepository, $domainPackRepository, $settingsRepository);
$auditLogger = new AuditLogger($runtimeRoot);
$changeService = new ChangeService(
    $entityRepository,
    $relationRepository,
    $recordSerializer,
    $pathGuard,
    $registryFileRepository,
    $resourceDependencyProjector,
    $changeRepository,
    $validator,
    $auditLogger,
);
$renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');

$web = new WebController($renderer, $entityRepository, $relationRepository, $domainPackRepository, $schemaRepository, $settingsRepository, $changeService);
$api = new ApiController($entityRepository, $relationRepository, $domainPackRepository, $schemaRepository, $settingsRepository, $changeService);

$router = new Router();
$router->add('GET', '/', [$web, 'dashboard']);
$router->add('GET', '/graph', [$web, 'graphPage']);

$router->add('GET', '/resources', [$web, 'entityList']);
$router->add('GET', '/entities', [$web, 'entityList']);
$router->add('GET', '/resources/new', [$web, 'newEntityForm']);
$router->add('GET', '/entities/new', [$web, 'newEntityForm']);
$router->add('POST', '/resources', [$web, 'upsertEntity']);
$router->add('POST', '/entities', [$web, 'upsertEntity']);
$router->add('GET', '/resources/{id}/dependencies/{slot}', [$web, 'editDependencySlotForm']);
$router->add('POST', '/resources/{id}/dependencies/{slot}', [$web, 'upsertDependencySlot']);
$router->add('GET', '/resources/{id}', [$web, 'entityDetail']);
$router->add('GET', '/entities/{id}', [$web, 'entityDetail']);
$router->add('GET', '/resources/{id}/edit', [$web, 'editEntityForm']);
$router->add('GET', '/entities/{id}/edit', [$web, 'editEntityForm']);
$router->add('POST', '/resources/{id}', [$web, 'upsertEntity']);
$router->add('POST', '/entities/{id}', [$web, 'upsertEntity']);

$router->add('GET', '/dependencies', [$web, 'relationList']);
$router->add('GET', '/relations', [$web, 'relationList']);
$router->add('GET', '/dependencies/new', [$web, 'newRelationForm']);
$router->add('GET', '/relations/new', [$web, 'newRelationForm']);
$router->add('POST', '/dependencies', [$web, 'upsertRelation']);
$router->add('POST', '/relations', [$web, 'upsertRelation']);
$router->add('GET', '/dependencies/{id}/edit', [$web, 'editRelationForm']);
$router->add('GET', '/relations/{id}/edit', [$web, 'editRelationForm']);
$router->add('POST', '/dependencies/{id}', [$web, 'upsertRelation']);
$router->add('POST', '/relations/{id}', [$web, 'upsertRelation']);

$router->add('GET', '/type-packs', [$web, 'domainPackList']);
$router->add('GET', '/domain-packs', [$web, 'domainPackList']);
$router->add('POST', '/type-packs/install', [$web, 'installTypePack']);
$router->add('POST', '/type-packs/{name}/enable', [$web, 'enableTypePack']);
$router->add('POST', '/type-packs/{name}/disable', [$web, 'disableTypePack']);
$router->add('POST', '/type-packs/{name}/uninstall', [$web, 'uninstallTypePack']);

$router->add('GET', '/changes', [$web, 'changeList']);
$router->add('GET', '/changes/{id}', [$web, 'changeDetail']);
$router->add('POST', '/changes/{id}/validate', [$web, 'validateChange']);
$router->add('POST', '/changes/{id}/save', [$web, 'saveChange']);
$router->add('POST', '/changes/{id}/commit', [$web, 'commitChange']);
$router->add('POST', '/changes/{id}/discard', [$web, 'discardChange']);
$router->add('POST', '/changes/{id}/abort', [$web, 'abortChange']);

$router->add('GET', '/validation', [$web, 'validationPage']);
$router->add('GET', '/settings', [$web, 'settingsPage']);
$router->add('POST', '/settings', [$web, 'upsertSettings']);

$router->add('GET', '/api/resources', [$api, 'resources']);
$router->add('POST', '/api/resources', [$api, 'createResource']);
$router->add('GET', '/api/entities', [$api, 'entities']);
$router->add('GET', '/api/resources/{id}', [$api, 'resource']);
$router->add('PATCH', '/api/resources/{id}', [$api, 'updateResource']);
$router->add('GET', '/api/entities/{id}', [$api, 'entity']);

$router->add('GET', '/api/dependencies', [$api, 'dependencies']);
$router->add('GET', '/api/relations', [$api, 'relations']);
$router->add('GET', '/api/graph', [$api, 'graph']);
$router->add('GET', '/api/entities/{id}/neighbors', [$api, 'entityNeighbors']);

$router->add('GET', '/api/types', [$api, 'types']);
$router->add('GET', '/api/schemas', [$api, 'schemas']);
$router->add('GET', '/api/settings', [$api, 'settings']);
$router->add('GET', '/api/resource-type-profiles', [$api, 'resourceTypeProfiles']);
$router->add('GET', '/api/resources/types/{type}/profile', [$api, 'resourceTypeProfile']);
$router->add('GET', '/api/resource-types/{type}/profile', [$api, 'resourceTypeProfile']);
$router->add('GET', '/api/tag-keys', [$api, 'tagKeys']);
$router->add('GET', '/api/search', [$api, 'search']);
$router->add('GET', '/api/resources/{id}/dependency-slots', [$api, 'resourceDependencySlots']);
$router->add('GET', '/api/entities/{id}/dependency-slots', [$api, 'resourceDependencySlots']);
$router->add('PUT', '/api/resources/{id}/dependencies/{slot}', [$api, 'putResourceDependencySlot']);
$router->add('PATCH', '/api/settings/tag-keys/{key}', [$api, 'patchTagKey']);

$router->add('GET', '/api/type-packs', [$api, 'typePacks']);
$router->add('GET', '/api/domain-packs', [$api, 'domainPacks']);
$router->add('GET', '/api/type-packs/installed', [$api, 'installedTypePacks']);
$router->add('GET', '/api/type-packs/available', [$api, 'availableTypePacks']);
$router->add('POST', '/api/type-packs/install', [$api, 'installTypePack']);
$router->add('POST', '/api/type-packs/{name}/enable', [$api, 'enableTypePack']);
$router->add('POST', '/api/type-packs/{name}/disable', [$api, 'disableTypePack']);
$router->add('POST', '/api/type-packs/{name}/uninstall', [$api, 'uninstallTypePack']);

$router->add('POST', '/api/changes', [$api, 'createChange']);
$router->add('GET', '/api/changes/{id}', [$api, 'getChange']);
$router->add('GET', '/api/changes/{id}/summary', [$api, 'changeSummary']);
$router->add('POST', '/api/changes/{id}/edits', [$api, 'addEdits']);
$router->add('POST', '/api/changes/{id}/operations', [$api, 'addOperations']);
$router->add('POST', '/api/changes/{id}/validate', [$api, 'validateChange']);
$router->add('GET', '/api/changes/{id}/diff', [$api, 'diffChange']);
$router->add('POST', '/api/changes/{id}/save', [$api, 'saveChange']);
$router->add('POST', '/api/changes/{id}/commit', [$api, 'commitChange']);
$router->add('POST', '/api/changes/{id}/discard', [$api, 'discardChange']);
$router->add('POST', '/api/changes/{id}/abort', [$api, 'abortChange']);

try {
    $request = Request::fromGlobals();
    $response = $router->dispatch($request);
} catch (Throwable $exception) {
    if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        $response = Response::json(['error' => $exception->getMessage()], 500);
    } else {
        $response = Response::html('Internal error: ' . h($exception->getMessage()), 500);
    }
}

$response->send();
