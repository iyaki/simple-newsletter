# Simple Newsletter

![Docker](https://img.shields.io/badge/docker-ready-blue)

**Simple Newsletter** is a free service that converts any Atom or RSS feed into an email newsletter. It allows users to subscribe to their favorite feeds and receive updates directly in their inbox.

## Table of Contents

- [Features](#features)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Deployment](#deployment)

## Features

- **Feed Conversion**: Converts Atom and RSS feeds into email newsletters.
- **Simple Subscription**: Easy-to-use subscription form for users.
- **Email Delivery**: Sends updates via SMTP.
- **Dockerized**: Fully containerized for easy deployment.
- **Open API**: Documented API for integration.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes.

### Prerequisites

- [Docker](https://www.docker.com/get-started)
- [Docker Compose](https://docs.docker.com/compose/install/)
- [Composer](https://getcomposer.org/) (optional, for local PHP dependency management)

### Installation

1.  **Clone the repository:**

    ```bash
    git clone https://github.com/iyaki/simple-newsletter.git
    cd simple-newsletter
    ```

2.  **Set up environment variables:**

    Copy the example environment file:

    ```bash
    cp example.env .env
    ```

    Edit `.env` with your configuration (SMTP settings, etc.).

3.  **Start the application:**

    ```bash
    docker compose up
    ```

    The application will be available at `http://localhost:8080`.

## Usage

Once the application is running, you can access the web interface to subscribe to feeds.

1.  Enter the Feed URL (Atom or RSS).
2.  Enter your Email address.
3.  Click "Subscribe!".

## API Documentation

The API is documented using OpenAPI. You can view the specification in `public/api-spec.yaml` or view it online at [Swagger Editor](https://editor.swagger.io/?url=https://simple-newsletter.com/api-spec.yaml).

## Deployment

### Production Setup

```bash
mkdir -p data/

docker run -d \
  --restart always \
  --env-file .env \ # optional, if you have a .env file or pass individual environment variables with -e flags
  -p 80:80 \ # required
  -p 443:443 \ # required
  -p 443:443/udp \ # required
  -v "$(pwd)/data:/app/data" \ # required
  -v caddy_data:/data \
  -v caddy_config:/config \
  ghcr.io/iyaki/simple-newsletter:latest
```

### Cron Job

To send newsletters automatically, set up a cron job on your server:

```cron
15 * * * * docker exec <container_name> php /app/bin/send-newsletters.php >> /root/send-newsletters.log
```

## Acknowledgments

- Inspired by [Kill the Newsletter](https://kill-the-newsletter.com/)
- Built with [FrankenPHP](https://frankenphp.dev/)
