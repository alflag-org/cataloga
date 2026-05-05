# UI/UX

Cataloga UI is a lightweight registry workspace focused on practical tasks.

Terminology is fixed by [docs/ui-terminology-ja.md](./ui-terminology-ja.md) for Japanese UI consistency.

## Navigation

- Dashboard
- Resources
- Dependencies
- Changes
- Type packs

## Resource flow

1. Choose resource type from installed type packs
2. Enter basic information and tags
3. Fill resource-specific `spec` fields from schema metadata
4. Review and create draft

### Tag UX

- Tags are key-value inputs.
- Basic tags are shown prominently (environment/owner/site/zone/lifecycle and schema-required tags).
- Additional custom tags are editable as key-value rows.
- Custom tag rows are added only when the user asks for another tag.
- Reserved prefixes are blocked in normal editing (`cataloga:`).

## Dependency flow

- Normal path: edit dependencies from resource detail using slot labels.
- Advanced path (`/dependencies/new`): manual source/type/target editing for exceptional cases.

## Change flow

- Review summary and validation
- Inspect technical diff when needed
- Save changes or discard

## UX principles used

- User-facing terminology (Resource/Dependency/Type pack/Draft)
- Guided select controls over raw IDs where possible
- Technical details collapsed behind Advanced sections
- Simple cards, tables, and readable forms
