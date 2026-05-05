<?php

declare(strict_types=1);

use Cataloga\Audit\AuditLogger;
use Cataloga\Git\GitService;
use Cataloga\Http\ApiController;
use Cataloga\Http\Request;
use Cataloga\Http\Response;
use Cataloga\Http\Router;
use Cataloga\Http\WebController;
use Cataloga\Mutation\ChangeService;
use Cataloga\Mutation\ChangeSessionRepository;
use Cataloga\Registry\EntityRepository;
use Cataloga\Registry\DomainPackRepository;
use Cataloga\Registry\PathGuard;
use Cataloga\Registry\RelationRepository;
use Cataloga\Registry\SchemaRepository;
use Cataloga\Registry\RecordParser;
use Cataloga\Registry\RecordSerializer;
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
$entityRepository = new EntityRepository($registryRoot, $recordParser, $recordSerializer, $pathGuard);
$relationRepository = new RelationRepository($registryRoot, $recordParser, $recordSerializer, $pathGuard);
$domainPackRepository = new DomainPackRepository($projectRoot, $recordParser);
$schemaRepository = new SchemaRepository($projectRoot, $registryRoot, $recordParser);
$changeRepository = new ChangeSessionRepository($runtimeRoot);
$validator = new RegistryValidator();
$gitService = new GitService($projectRoot);
$auditLogger = new AuditLogger($runtimeRoot);
$changeService = new ChangeService(
    $entityRepository,
    $relationRepository,
    $recordSerializer,
    $pathGuard,
    $changeRepository,
    $validator,
    $gitService,
    $auditLogger,
);
$renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');

$web = new WebController($renderer, $entityRepository, $relationRepository, $domainPackRepository, $schemaRepository, $changeService, $gitService);
$api = new ApiController($entityRepository, $relationRepository, $domainPackRepository, $schemaRepository, $changeService);

$router = new Router();
$router->add('GET', '/', [$web, 'dashboard']);
$router->add('GET', '/graph', [$web, 'graphPage']);
$router->add('GET', '/entities', [$web, 'entityList']);
$router->add('GET', '/relations', [$web, 'relationList']);
$router->add('GET', '/relations/new', [$web, 'newRelationForm']);
$router->add('POST', '/relations', [$web, 'upsertRelation']);
$router->add('GET', '/relations/{id}/edit', [$web, 'editRelationForm']);
$router->add('POST', '/relations/{id}', [$web, 'upsertRelation']);
$router->add('GET', '/domain-packs', [$web, 'domainPackList']);
$router->add('GET', '/changes', [$web, 'changeList']);
$router->add('GET', '/entities/new', [$web, 'newEntityForm']);
$router->add('POST', '/entities', [$web, 'upsertEntity']);
$router->add('GET', '/entities/{id}', [$web, 'entityDetail']);
$router->add('GET', '/entities/{id}/edit', [$web, 'editEntityForm']);
$router->add('POST', '/entities/{id}', [$web, 'upsertEntity']);
$router->add('GET', '/changes/{id}', [$web, 'changeDetail']);
$router->add('POST', '/changes/{id}/validate', [$web, 'validateChange']);
$router->add('POST', '/changes/{id}/commit', [$web, 'commitChange']);
$router->add('POST', '/changes/{id}/abort', [$web, 'abortChange']);
$router->add('GET', '/validation', [$web, 'validationPage']);
$router->add('GET', '/git/diff', [$web, 'gitDiffPage']);

$router->add('GET', '/api/entities', [$api, 'entities']);
$router->add('GET', '/api/relations', [$api, 'relations']);
$router->add('GET', '/api/domain-packs', [$api, 'domainPacks']);
$router->add('GET', '/api/entities/{id}', [$api, 'entity']);
$router->add('GET', '/api/entities/{id}/neighbors', [$api, 'entityNeighbors']);
$router->add('GET', '/api/relations', [$api, 'relations']);
$router->add('GET', '/api/schemas', [$api, 'schemas']);
$router->add('GET', '/api/search', [$api, 'search']);
$router->add('POST', '/api/changes', [$api, 'createChange']);
$router->add('GET', '/api/changes/{id}', [$api, 'getChange']);
$router->add('GET', '/api/changes/{id}/summary', [$api, 'changeSummary']);
$router->add('POST', '/api/changes/{id}/operations', [$api, 'addOperations']);
$router->add('POST', '/api/changes/{id}/validate', [$api, 'validateChange']);
$router->add('GET', '/api/changes/{id}/diff', [$api, 'diffChange']);
$router->add('POST', '/api/changes/{id}/commit', [$api, 'commitChange']);
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
