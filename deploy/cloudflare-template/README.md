# Cataloga Cloudflare Template

This repository is a generated Cloudflare Deploy Button template for Cataloga.

Do not edit this repository directly. Changes should be made in the main repository:

https://github.com/viasnake/cataloga

## Deploy

[![Deploy to Cloudflare](https://deploy.workers.cloudflare.com/button)](https://deploy.workers.cloudflare.com/?url=https://github.com/viasnake/cataloga-cloudflare-template)

## What this deploys

- Cloudflare Worker
- Cloudflare Workers Static Assets
- Cloudflare D1 database
- Cloudflare R2 bucket

## Storage

- D1 is used as Cataloga's primary runtime database.
- R2 is used for snapshots and export-like durable artifacts.

## Security

Cataloga may contain infrastructure inventory, resource names, relations, ownership, and operational state.

For production use, protect the deployed Worker with Cloudflare Access or equivalent authentication. Do not expose sensitive infrastructure catalogs publicly without access control.

## Upgrades

This template is updated from the main Cataloga repository release workflow.

To upgrade an existing deployment, pull the latest changes from this generated repository and redeploy from Cloudflare Workers Builds, or follow the upgrade guide in the main Cataloga repository when available.
