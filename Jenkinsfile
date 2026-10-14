
pipeline {
    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
        timeout(time: 30, unit: 'MINUTES')
    }

    environment {
        APP_IMAGE = 'online-shop-app-ci:latest'
        APP_CONTAINER = 'online-shop-app-ci'

        MYSQL_CONTAINER = 'online-shop-mysql-ci'
        MYSQL_IMAGE = 'mysql:8.0'

        NETWORK = 'online-shop-ci-network'
        MYSQL_VOLUME = 'online-shop-mysql-ci-data'

        APP_PORT = '8091'

        MYSQL_DATABASE = 'online_shop'
        MYSQL_USER = 'online_shop'
        MYSQL_PASSWORD = 'online_shop_password'
        MYSQL_ROOT_PASSWORD = 'root_password'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm

                sh '''
                    set -eu

                    echo "========== PROJECT CHECKOUT =========="
                    echo "Workspace: $WORKSPACE"

                    git log -1 --oneline

                    echo "========== REQUIRED FILES =========="

                    test -f Dockerfile
                    test -f composer.json
                    test -f artisan
                    test -f routes/web.php

                    echo "Required Laravel files are present."
                '''
            }
        }

        stage('Prepare Docker Environment') {
            steps {
                sh '''
                    set -eu

                    echo "========== PREPARING DOCKER =========="

                    docker --version
                    docker compose version || true

                    echo "Removing stale CI resources, if any..."

                    docker rm -f "$APP_CONTAINER" >/dev/null 2>&1 || true
                    docker rm -f "$MYSQL_CONTAINER" >/dev/null 2>&1 || true

                    docker network rm "$NETWORK" >/dev/null 2>&1 || true
                    docker volume rm "$MYSQL_VOLUME" >/dev/null 2>&1 || true

                    docker network create "$NETWORK"
                    docker volume create "$MYSQL_VOLUME"

                    echo "Docker network and database volume are ready."
                '''
            }
        }

        stage('Build Laravel Image') {
            steps {
                sh '''
                    set -eu

                    echo "========== BUILDING LARAVEL IMAGE =========="

                    docker build \
                        --build-arg INSTALL_DEV=true \
                        --tag "$APP_IMAGE" \
                        .

                    echo "Laravel Docker image built successfully."
                '''
            }
        }

        stage('Start MySQL') {
            steps {
                sh '''
                    set -eu

                    echo "========== STARTING MYSQL =========="

                    docker run -d \
                        --name "$MYSQL_CONTAINER" \
                        --network "$NETWORK" \
                        --network-alias mysql \
                        --mount "source=$MYSQL_VOLUME,target=/var/lib/mysql" \
                        -e MYSQL_DATABASE="$MYSQL_DATABASE" \
                        -e MYSQL_USER="$MYSQL_USER" \
                        -e MYSQL_PASSWORD="$MYSQL_PASSWORD" \
                        -e MYSQL_ROOT_PASSWORD="$MYSQL_ROOT_PASSWORD" \
                        "$MYSQL_IMAGE"

                    echo "Waiting for MySQL to become ready..."

                    attempt=1
                    max_attempts=150

                    until docker exec "$MYSQL_CONTAINER" \
                        mysqladmin ping \
                        --socket=/var/run/mysqld/mysqld.sock \
                        --user="$MYSQL_USER" \
                        "--password=$MYSQL_PASSWORD" \
                        --silent >/dev/null 2>&1
                    do
                        if [ "$attempt" -ge "$max_attempts" ]; then
                            echo "ERROR: MySQL did not become ready."
                            docker logs --tail 150 "$MYSQL_CONTAINER" || true
                            exit 1
                        fi

                        echo "MySQL is starting (attempt $attempt/$max_attempts)..."
                        attempt=$((attempt + 1))
                        sleep 2
                    done

                    echo "MySQL server is responding."

                    echo "========== MYSQL ROOT DIAGNOSTICS =========="
                    docker exec "$MYSQL_CONTAINER" \
                        mysql \
                        --user=root \
                        "--password=$MYSQL_ROOT_PASSWORD" \
                        --execute="SELECT VERSION();"

                    echo "========== MYSQL ACCOUNT DETAILS =========="
                    docker exec "$MYSQL_CONTAINER" \
                        mysql \
                        --user=root \
                        "--password=$MYSQL_ROOT_PASSWORD" \
                        --execute="SELECT User, Host, plugin FROM mysql.user WHERE User='$MYSQL_USER';"

                    echo "========== MYSQL USER GRANTS =========="
                    docker exec "$MYSQL_CONTAINER" \
                        mysql \
                        --user=root \
                        "--password=$MYSQL_ROOT_PASSWORD" \
                        --execute="SHOW GRANTS FOR '$MYSQL_USER'@'%';" || true

                    echo "========== TESTING APPLICATION CREDENTIALS =========="
                    docker exec "$MYSQL_CONTAINER" \
                        mysql \
                        --user="$MYSQL_USER" \
                        "--password=$MYSQL_PASSWORD" \
                        --execute="SELECT VERSION();"

                    echo "Verifying MySQL user and database..."
                    docker exec "$MYSQL_CONTAINER" \
                        mysql \
                        --user="$MYSQL_USER" \
                        "--password=$MYSQL_PASSWORD" \
                        --execute="SELECT User, Host FROM mysql.user WHERE User='$MYSQL_USER';"
                    docker exec "$MYSQL_CONTAINER" \
                        mysql \
                        --user="$MYSQL_USER" \
                        "--password=$MYSQL_PASSWORD" \
                        --execute="SHOW DATABASES LIKE '$MYSQL_DATABASE';"
                    docker exec "$MYSQL_CONTAINER" \
                        mysql \
                        --user="$MYSQL_USER" \
                        "--password=$MYSQL_PASSWORD" \
                        --execute="SELECT 1;"
                '''
            }
        }

        stage('Start Laravel Application') {
            steps {
                sh '''
                    set -eu

                    echo "========== STARTING LARAVEL =========="

                    APP_KEY_VALUE="$(docker run --rm "$APP_IMAGE" php -r 'echo "base64:".base64_encode(random_bytes(32));')"

                    docker run -d \
                        --name "$APP_CONTAINER" \
                        --network "$NETWORK" \
                        --publish "127.0.0.1:$APP_PORT:8000" \
                        -e APP_ENV=testing \
                        -e APP_DEBUG=true \
                        -e APP_KEY="$APP_KEY_VALUE" \
                        -e APP_URL="http://localhost:$APP_PORT" \
                        -e LOG_CHANNEL=stderr \
                        -e LOG_LEVEL=debug \
                        -e DB_CONNECTION=mysql \
                        -e DB_HOST=mysql \
                        -e DB_PORT=3306 \
                        -e DB_DATABASE="$MYSQL_DATABASE" \
                        -e DB_USERNAME="$MYSQL_USER" \
                        -e DB_PASSWORD="$MYSQL_PASSWORD" \
                        "$APP_IMAGE"

                    echo "Waiting for Laravel to start..."

                    attempt=1
                    max_attempts=60

                    until docker exec "$APP_CONTAINER" \
                        php artisan --version >/dev/null 2>&1
                    do
                        if [ "$attempt" -ge "$max_attempts" ]; then
                            echo "ERROR: Laravel did not start correctly."
                            docker logs --tail 150 "$APP_CONTAINER" || true
                            exit 1
                        fi

                        echo "Laravel is starting (attempt $attempt/$max_attempts)..."
                        attempt=$((attempt + 1))
                        sleep 2
                    done

                    echo "Laravel application container is running."
                    docker exec "$APP_CONTAINER" php artisan --version
                '''
            }
        }

        stage('Wait for Database Connection') {
            steps {
                sh '''
                    set -eu

                    echo "========== CHECKING DATABASE CONNECTION =========="

                    attempt=1
                    max_attempts=60

                    until docker exec "$APP_CONTAINER" \
                        php artisan db:show >/dev/null 2>&1
                    do
                        if [ "$attempt" -ge "$max_attempts" ]; then
                            echo "========== LARAVEL DATABASE ERROR =========="

                            docker exec "$APP_CONTAINER" \
                                php artisan migrate:status 2>&1 || true

                            echo "========== TESTING MYSQL CONNECTIVITY =========="
                            docker exec "$APP_CONTAINER" \
                                mysql --ssl-mode=DISABLED --host=mysql --user="$MYSQL_USER" --password="$MYSQL_PASSWORD" --execute="SELECT 1;" 2>&1 || true

                            echo "========== MYSQL CONTAINER LOGS =========="
                            docker logs --tail 100 "$MYSQL_CONTAINER" || true

                            echo "========== LARAVEL CONTAINER LOGS =========="
                            docker logs --tail 100 "$APP_CONTAINER" || true

                            exit 1
                        fi

                        echo "Waiting for database connection (attempt $attempt/$max_attempts)..."
                        attempt=$((attempt + 1))
                        sleep 2
                    done

                    echo "Laravel can connect to MySQL."
                '''
            }
        }

        stage('Run Database Migrations') {
            steps {
                sh '''
                    set -eu

                    echo "========== RUNNING MIGRATIONS =========="

                    docker exec "$APP_CONTAINER" \
                        php artisan migrate --force

                    echo "Database migrations completed successfully."
                '''
            }
        }

        stage('Run Laravel Tests') {
            steps {
                sh '''
                    set -eu

                    echo "========== RUNNING AUTOMATED TESTS =========="

                    docker exec "$APP_CONTAINER" \
                        php artisan test

                    echo "Laravel tests passed."
                '''
            }
        }

        stage('Laravel Internal Health Check') {
            steps {
                sh '''
                    set -eu

                    echo "========== CHECKING LARAVEL /UP =========="

                    attempt=1
                    max_attempts=30

                    until docker run --rm \
                        --network "$NETWORK" \
                        curlimages/curl:latest \
                        -fsS --connect-timeout 5 \
                        "http://$APP_CONTAINER:8000/up" \
                        >/dev/null
                    do
                        if [ "$attempt" -ge "$max_attempts" ]; then
                            echo "ERROR: Laravel /up endpoint failed."
                            docker logs --tail 150 "$APP_CONTAINER" || true
                            exit 1
                        fi

                        echo "Waiting for /up endpoint (attempt $attempt/$max_attempts)..."
                        attempt=$((attempt + 1))
                        sleep 2
                    done

                    echo "Laravel /up endpoint is healthy."
                '''
            }
        }

        stage('HTTP Homepage Health Check') {
            steps {
                sh '''
                    set -u

                    echo "========== CHECKING HOMEPAGE =========="

                    if docker run --rm \
                        --network "$NETWORK" \
                        curlimages/curl:latest \
                        --silent \
                        --show-error \
                        --fail \
                        --connect-timeout 10 \
                        --max-time 30 \
                        --output /dev/null \
                        --write-out "HTTP status: %{http_code}\\n" \
                        "http://$APP_CONTAINER:8000/"
                    then
                        echo "Homepage returned a successful HTTP response."
                    else
                        RESULT=$?

                        echo "ERROR: Homepage request failed with exit code $RESULT."

                        echo "========== LARAVEL LOG FILE =========="

                        docker exec "$APP_CONTAINER" sh -c '
                            if [ -f storage/logs/laravel.log ]; then
                                tail -n 150 storage/logs/laravel.log
                            else
                                echo "No storage/logs/laravel.log file found."
                            fi
                        ' || true

                        echo "========== LARAVEL CONTAINER LOGS =========="
                        docker logs --tail 200 "$APP_CONTAINER" 2>&1 || true

                        echo "========== STORAGE AND CACHE PERMISSIONS =========="

                        docker exec "$APP_CONTAINER" sh -c '
                            ls -ld storage storage/logs \
                                storage/framework \
                                storage/framework/cache \
                                storage/framework/sessions \
                                storage/framework/views \
                                bootstrap/cache 2>&1 || true

                            echo "========== VITE BUILD MANIFEST =========="

                            if [ -f public/build/manifest.json ]; then
                                echo "Vite manifest exists."
                                ls -l public/build/manifest.json
                            else
                                echo "Vite manifest is missing from the container."
                            fi

                            if [ -f public/hot ]; then
                                echo "WARNING: public/hot exists in the container."
                                cat public/hot
                            else
                                echo "No Vite hot-file detected."
                            fi
                        ' || true

                        echo "========== END HOMEPAGE DIAGNOSTICS =========="
                        exit "$RESULT"
                    fi
                '''
            }
        }
    }

    post {
        always {
            sh '''
                echo "========== JENKINS CLEANUP =========="

                if docker container inspect "$APP_CONTAINER" >/dev/null 2>&1; then
                    echo "Final application container status:"
                    docker inspect \
                        --format='Status={{.State.Status}} ExitCode={{.State.ExitCode}}' \
                        "$APP_CONTAINER" || true

                    echo "Final application logs:"
                    docker logs --tail 80 "$APP_CONTAINER" 2>&1 || true
                fi

                if docker container inspect "$MYSQL_CONTAINER" >/dev/null 2>&1; then
                    echo "Final MySQL logs:"
                    docker logs --tail 40 "$MYSQL_CONTAINER" 2>&1 || true
                fi

                docker rm -f "$APP_CONTAINER" >/dev/null 2>&1 || true
                docker rm -f "$MYSQL_CONTAINER" >/dev/null 2>&1 || true

                docker network rm "$NETWORK" >/dev/null 2>&1 || true
                docker volume rm "$MYSQL_VOLUME" >/dev/null 2>&1 || true

                echo "Cleanup complete."
            '''
        }
    }
}

