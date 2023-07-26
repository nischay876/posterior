## Setup

To configure the web app, perform the following steps:

1. Rename the `.env.example` file to `.env`.
2. Open the `.env` file and update the necessary variables with your configuration.
3. Update the database credentials, Discord OAuth settings, Cloudflare Turnstile configuration, and any other relevant variables.
4. Create Table In database
```sql
CREATE TABLE "users1" (
    "id" INTEGER NOT NULL DEFAULT 0,
    "name" VARCHAR(100) NULL DEFAULT NULL,
    "email" VARCHAR(100) NOT NULL,
    "password" VARCHAR(100) NULL DEFAULT NULL,
    "discord_id" VARCHAR(100) NULL DEFAULT NULL,
    "username" VARCHAR(100) NULL DEFAULT NULL,
    "api_key" VARCHAR NULL DEFAULT NULL,
    "verification_code" VARCHAR NULL DEFAULT NULL,
    "is_verified" VARCHAR NULL DEFAULT NULL,
    "unid" VARCHAR NULL DEFAULT NULL,
    PRIMARY KEY ("id")
);
```