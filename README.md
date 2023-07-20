# Frontend Web App Posterior

This README provides an overview and instructions for a frontend web application that incorporates various features including register, login, Discord authentication, Cloudflare Turnstile, password login/magic link, and the ability to upload images through the web. It also includes an image and video manager.

## Table of Contents

- [Features](#features)
- [Getting Started](#getting-started)
- [Dependencies](#dependencies)
- [Setup](#setup)
- [Usage](#usage)
- [Contributing](#contributing)
- [License](#license)

## Features

- Register: Allow users to create new accounts by providing their desired username, email, and password.
- Login: Enable registered users to log in to their accounts using their credentials.
- Discord Authentication: Provide the option for users to log in using their Discord account through Discord OAuth for secure authentication.
- Cloudflare Turnstile: Integrate with Cloudflare Turnstile for enhanced security and protection against malicious traffic and attacks.
- Password Login/Magic Link: Allow users to choose between traditional password-based login or magic link-based login for convenience and security.
- Upload Image Through Web: Enable users to upload images directly through the web interface.
- Image and Video Manager: Provide functionality to manage uploaded images and videos, including viewing, organizing, and deleting.

## Getting Started

To get started with the web app, follow these instructions:

1. Clone the repository: `git clone https://github.com/nischay876/posterior.git` or `wget https://github.com/nischay876/posterior/releases/latest/download/posterior.zip && unzip posterior.zip`
2. Install the necessary dependencies by running `composer install`.
3. Configure environment variables using `.env` file (see [Setup](#setup) section).
4. Run a local web server or deploy the app to a web hosting service.

## Dependencies

This project relies on the following dependencies:

- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv): A library for loading environment variables from a `.env` file.
- [phpmailer/phpmailer](https://github.com/PHPMailer/PHPMailer): A full-featured email creation and transfer class for PHP.

## Setup

To configure the web app, perform the following steps:

1. Rename the `.env.example` file to `.env`.
2. Open the `.env` file and update the necessary variables with your configuration.
3. Update the database credentials, Discord OAuth settings, Cloudflare Turnstile configuration, and any other relevant variables.
4. Create Table In database
```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    userr_id VARCHAR(50),
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    apikey VARCHAR(255) NOT NULL DEFAULT '',
    password VARCHAR(255) NOT NULL,
    verification_code VARCHAR(255) DEFAULT NULL,
    timestamp VARCHAR(255) NOT NULL,
    is_verified BOOLEAN DEFAULT false,
    reset_code VARCHAR(50),
    magiccode VARCHAR(50),
    suspended BOOLEAN DEFAULT false
);
```
5. Create a Col


## Usage

Provide instructions on how to use and interact with the web app. Include any specific steps or considerations for each feature mentioned in the [Features](#features) section.

## Contributing

If you would like to contribute to this project, please follow these steps:

1. Fork the repository.
2. Create a new branch for your changes.
3. Make your modifications and commit them.
4. Push your changes to your fork.
5. Submit a pull request detailing your changes and any relevant information.

## License

```
ISC License

Copyright (c) 2021, nischay sharma

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
```

