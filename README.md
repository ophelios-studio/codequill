# ZEPHYRUS Pulsar Boilerplate Project

Project intended to be used as a template base for ZEPHYRUS projects.

## Development Environment (Docker)

### Prerequisites
Make sure you have the [Docker Engine](https://www.docker.com/products/docker-desktop/) installed and up to date.

### First Start
Copy the `.env.example` file to a file named `.env`. Finally, launch the development environment build.

```ssh
docker compose up
docker exec -it zephyrus_webserver composer install
```

### Update Dependencies (Composer)
```ssh
docker exec -it zephyrus_webserver composer update
```


### Restart Database (if needed)
```ssh
docker compose down
docker compose up
```


### Enable / Disable Xdebug
By default, xdebug is installed but not active to increase development performance. However, it is possible to enable and disable it with a command. Must be executed on the host computer and not from the Docker container (since the script interacts with the Docker executable on the host).

#### Enable
```ssh
composer xdebug-enable
```


#### Disable
```ssh
composer xdebug-disable
```


### Generate Latte Cache
```ssh
docker exec -it zephyrus_webserver composer latte-cache
```


### Remove Docker Images
```ssh
docker rmi $(docker images -q)
```


## MailCatcher

By default, the Docker image provided with Zephyrus includes [MailCatcher](https://www.google.com/search?client=safari&rls=en&q=mailcatcher&ie=UTF-8&oe=UTF-8). This allows for simple email testing.

To access MailCatcher: http://localhost:1080/

```yml
mailer:
  transport: "smtp"
  default_from_address: "info@ophelios.com"
  default_from_name: "Zephyrus"
  smtp:
    enabled: true
    host: "localhost"
    port: 1025
    encryption: "none"
    username: ""
    password: ""
```
