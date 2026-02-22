# 16-Add Background Task Queues
#### Enhance the application by incorporating background task queues for sending notifications and fetching weather updates.

## Links
#### URL of the deployed application
https://symfonyweatherreminder.mooo.com  
https://www.symfonyweatherreminder.mooo.com  
https://api.symfonyweatherreminder.mooo.com
#### Swagger (OpenAPI) documentation
https://www.symfonyweatherreminder.mooo.com/api/doc
#### Mailpit web UI
http://176.117.78.133:8025/
#### RabbitMQ web UI
http://176.117.78.133:15672/ (user: app, password: 1077)
#### phpMyAdmin
http://176.117.78.133:8090/ (user: root, password: 1077)

## System requirements:
linux kernel version 6.17.0-12-generic  
docker engine version 29.2.1  
docker compose version 5.0.2  
unoccupied ports 80(HTTP) 443(HTTPS) 8025(Mailpit web UI) 8090(phpMyAdmin) 8306(MySQL for IDE) 15672(RabbitMQ web UI)  

## Key architectural characteristics:
PHP 8.4  
Symfony 8.0.2  
Web (Twig-based) and API (JSON) interfaces  
Stateless API secured with lexik/jwt-authentication-bundle  
External Weather API integration  
Persistent weather data caching in MySQL   
Asynchronous message processing via Symfony Messenger with RabbitMQ

## Application overview
**Symfony Weather Reminder** is a weather-fetching gateway providing data caching, user subscription management, and automated alerts triggered by temperature thresholds.  
This application operates entirely within a Docker container environment and is based on the [php-docker-dev-env](https://github.com/satnetuser001/php-docker-dev-env) GitHub repository.
#### Web UI
The web interface provides a high-level project overview, direct links to the interactive [Swagger (OpenAPI) documentation](https://www.symfonyweatherreminder.mooo.com/api/doc), and access to the [Mailpit](http://176.117.78.133:8025/), [RabbitMQ](http://176.117.78.133:15672/) and [phpMyAdmin](http://176.117.78.133:8090/) service containers.
#### API
The core functionality of the application is available via API endpoints. Interactive documentation and testing are available at [Swagger UI](https://www.symfonyweatherreminder.mooo.com/api/doc).
*   **Authentication & Users**
    *   `POST /api/v1/users`: Register a new user with email and password.
    *   `POST /api/login_check`: Exchange credentials for a JWT Bearer token.
*   **Weather Data**
    *   `GET /api/v1/weather/{city}`: Retrieve current weather data for a specific location (uses Cache-Aside).
*   **Subscriptions**
    *   `GET /api/v1/subscriptions`: List all active weather subscriptions for the authenticated user.
    *   `POST /api/v1/subscriptions`: Subscribe to a city with specific temperature thresholds.
    *   `DELETE /api/v1/subscriptions/{id}`: Remove an existing weather subscription.

## Application core logic
#### 1. User authentication (JWT)
The system implements a stateless security model via the `lexik/jwt-authentication-bundle`.
*   **Registration:** Handled by `UserApiController::store()`, where passwords are securely hashed using `UserPasswordHasherInterface`.
*   **Authentication:** The `/api/login_check` endpoint validates credentials and returns a JWT token. This token must be included in the `Authorization` header for all subsequent protected requests.
#### 2. Weather orchestration & Caching
The application optimizes external API usage through a **Cache-Aside** pattern managed by the `WeatherService`.
*   **Caching:** Weather data is stored in the `WeatherCache` entity. The service first checks if fresh data (defined by `WEATHER_CACHE_TTL_MINUTES`) exists in the local database via `WeatherCacheRepository`.
*   **Providers:** If the cache is stale, the `ExternalWeatherApiProvider` fetches data from the external service, converts it into a `WeatherData` DTO, and updates the local cache.
#### 3. Subscription & Threshold logic
Users manage their monitoring preferences through `SubscriptionController`. Each `Subscription` entity links a user to a city and defines temperature boundaries via `tempLowerBoundary` and `tempUpperBoundary`.  
To prevent notification spam, the system implements a **Stateful Alerting** mechanism using `isLowerTriggered` and `isUpperTriggered` flags:
*   **Trigger Activation:** When the temperature crosses a boundary (e.g., falls below the lower threshold), the system checks the corresponding flag. If the flag is `false`, a notification is sent, and the flag is set to `true`.
*   **Spam Suppression:** As long as the temperature remains outside the "normal" range, the flag stays `true`. Subsequent checks will see this state and skip sending additional notifications, ensuring the user receives only one alert per event.
*   **Trigger Reset:** The flag is reset to `false` only when the temperature returns to the safe range (e.g., rises back above the lower boundary). This "re-arms" the system, allowing it to send a new alert the next time the temperature drops again.
#### 4. Background notification pipeline
The core alerting logic is decoupled from the web server and executed through an asynchronous pipeline using **Symfony Messenger** and **RabbitMQ**. This process is distributed across specialized worker containers to ensure high availability and independent scaling.
*   **Step 1: Sync (`scheduler` container):**
    The `WeatherSyncScheduleProvider` periodically dispatches a global synchronization message. The `worker-sync-weather` container consumes this message and identifies cities in the `WeatherCache` that require an update based on their **TTL (Time-To-Live)** (configured via `WEATHER_CACHE_TTL_MINUTES`). For every "stale" city found, the worker dispatches a new individual update message back to **RabbitMQ**, effectively triggering the next stage of the pipeline.
*   **Step 2: Update (`worker-sync-weather` container):**
    Reacting to the individual update messages from the previous step, this worker fetches fresh data from the external API, populates the `WeatherData` DTO, and updates the corresponding database record in `WeatherCache`. Upon a successful update, it dispatches a trigger detection message to **RabbitMQ**, signaling that the new data is ready for analysis against user subscriptions.
*   **Step 3: Detect (`worker-detect-triggers` container):**
    This worker reacts to the "data updated" signal. It compares the new temperature against each user's `Subscription` settings. If a threshold is crossed for the first time (according to the **Stateful Alerting** logic), the worker sets the corresponding `isTriggered` flag to `true` and dispatches a message to **RabbitMQ** to request a notification for the user.
*   **Step 4: Notify & Email (`worker-notifications` & `worker-emails` containers):**
    Upon receiving a notification request, the `worker-notifications` container first persists a permanent record in the database via the `Notification` entity to maintain the user's alert history. Only after the record is saved, a final task is dispatched to the `worker-emails` container, which executes the delivery via **Symfony Mailer**. All outgoing messages are captured and can be inspected through the [Mailpit](http://176.117.78.133:8025/) web interface.

## Initial Setup
Clone the repository
```bash
git clone git@git.foxminded.ua:foxstudent108512/task-16-add-background-task-queues.git symfonyweatherreminder.mooo.com
```
or
```bash
git clone -b dev git@git.foxminded.ua:foxstudent108512/task-16-add-background-task-queues.git symfonyweatherreminder.mooo.com
```
Navigate to the `symfonyweatherreminder.mooo.com` directory and run Docker Compose passing the Current User ID and the Current Group ID
```bash
CUID=$(id -u) CGID=$(id -g) docker compose up -d
```
Replace the dummy SSL certificate (required for the initial Nginx startup) with a valid one issued via the Certbot
```bash
rm -rf nginx/ssl-certificates/live/symfonyweatherreminder.mooo.com && docker compose --profile certbot run --rm certbot && docker compose restart nginx
```

## Renewing the SSL certificate after the 90-day expiration period
```bash
docker compose --profile certbot run --rm certbot && docker compose restart nginx
```

## Symfony setup
Enter in cli container
```bash
docker exec -it cli bash
```
Create .env.local
```bash
cp .env.local.example .env.local
```
Generate APP_SECRET in .env.local
```bash
./generate-app-secret.sh
```
Install project dependencies
```bash
composer install
```
Generate JWT Keypair
```bash
bin/console lexik:jwt:generate-keypair
```
Create database schema
```bash
bin/console doctrine:migrations:migrate
```
Seed the database with test data via fixtures
```bash
bin/console doctrine:fixtures:load --no-interaction
```

## Testing
The project includes unit and functional tests to ensure code quality and reliability. All tests are executed inside the `cli` container.  
Enter in cli container
```bash
docker exec -it cli bash
```
Run all tests with TestDox format output
```bash
bin/phpunit --testdox
```
Write code coverage report in HTML format to directory
```bash
bin/phpunit --coverage-html var/coverage
```
The HTML report will be available in the `project/var/coverage` directory.
