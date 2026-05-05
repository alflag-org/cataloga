use clap::{Parser, Subcommand};
use std::{fs, net::SocketAddr};

#[derive(Parser)]
struct Cli {
    #[command(subcommand)]
    command: Command,
}

#[derive(Subcommand)]
enum Command {
    Init,
    Serve {
        #[arg(long, default_value = "sqlite://./cataloga.db")]
        db: String,
        #[arg(long, default_value = "127.0.0.1:8080")]
        listen: SocketAddr,
        #[arg(long, default_value = "default")]
        catalog: String,
    },
    Migrate {
        #[arg(long, default_value = "sqlite://./cataloga.db")]
        db: String,
    },
    Seed {
        #[arg(long, default_value = "sqlite://./cataloga.db")]
        db: String,
        #[arg(long, default_value = "default")]
        catalog: String,
    },
    Import {
        path: String,
        #[arg(long, default_value = "sqlite://./cataloga.db")]
        db: String,
        #[arg(long, default_value = "default")]
        catalog: String,
    },
    Export {
        path: String,
        #[arg(long, default_value = "sqlite://./cataloga.db")]
        db: String,
        #[arg(long, default_value = "default")]
        catalog: String,
    },
    Validate {
        #[arg(long, default_value = "sqlite://./cataloga.db")]
        db: String,
        #[arg(long, default_value = "default")]
        catalog: String,
    },
}

#[tokio::main]
async fn main() -> anyhow::Result<()> {
    let cli = Cli::parse();

    match cli.command {
        Command::Serve {
            db,
            listen,
            catalog,
        } => cataloga_server::serve(db, listen, catalog).await?,
        Command::Init => {
            println!("initialized");
        }
        Command::Migrate { db } => {
            let _ = cataloga_store_sqlite::SqliteStore::connect(&db).await?;
            println!("migrated");
        }
        Command::Seed { db, catalog } => {
            let store = cataloga_store_sqlite::SqliteStore::connect(&db).await?;
            let api = cataloga_api::ApiService::new(store);
            let input = fs::read_to_string("examples/home-lab/registry/export.yaml")?;
            api.import_catalog_yaml(&catalog, &input).await?;
            println!("seeded");
        }
        Command::Import { path, db, catalog } => {
            let store = cataloga_store_sqlite::SqliteStore::connect(&db).await?;
            let api = cataloga_api::ApiService::new(store);
            let input = fs::read_to_string(path)?;
            api.import_catalog_yaml(&catalog, &input).await?;
            println!("imported");
        }
        Command::Export { path, db, catalog } => {
            let store = cataloga_store_sqlite::SqliteStore::connect(&db).await?;
            let api = cataloga_api::ApiService::new(store);
            let output = api.export_catalog_yaml(&catalog).await?;
            fs::write(path, output)?;
            println!("exported");
        }
        Command::Validate { db, catalog } => {
            let store = cataloga_store_sqlite::SqliteStore::connect(&db).await?;
            let api = cataloga_api::ApiService::new(store);
            api.validate_catalog(&catalog).await?;
            println!("validated");
        }
    }

    Ok(())
}
