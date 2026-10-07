pipeline {
    agent any

    environment {
        APP_CONTAINER = 'online-shop-app-ci'
        MYSQL_CONTAINER = 'online-shop-mysql-ci'
        NETWORK = 'online-shop-ci-network'
        MYSQL_VOLUME = 'online-shop-mysql-ci-volume'
        APP_PORT = '8091'
    }

    stages {

        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build Laravel Image') {
            steps {
                sh '''
                    set -e

                    echo "=== Building Laravel Docker image ==="

                    docker build \
                        --build-arg INSTALL_DEV=true \
                        -t online-shop-app-ci:latest \
                        .
                '''
            }
        }

        stage('Create CI Network') {
            steps {
                sh '''
                    set -e

                    docker network rm "$NETWORK" >/dev/null 2>&1 || true
                    docker network create "$NETWORK"
                '''
            }
        }

        stage('Start MySQL') {
            steps {
                sh '''
                    set -e

                    docker volume rm "$MYSQL_VOLUME" >/dev/null 2>&1 || true
                    docker volume create "$MYSQL_VOLUME"

                    docker rm -f "$MYSQL_CONTAINER" >/dev/null 2>&1 || true

                    docker run -d \
                        --name "$MYSQL_CONTAINER" \
                        --network "$NETWORK" \
                        --network-alias mysql \
                        --mount "source=$MYSQL_VOLUME,target=/var/lib/mysql" \
                        -e MYSQL_DATABASE=online_shop \
                        -e MYSQL_USER=online_shop \
                        -e MYSQL_PASSWORD=online_shop_password \
                        -e MYSQL_ROOT_PASSWORD=root_password \
                        mysql:8.0

                    echo "=== Waiting for MySQL to initialize (initial delay) ==="
                    sleep 15

                    echo "=== Waiting for MySQL ==="

                    for i in $(seq 1 90); do
                        if docker exec "$MYSQL_CONTAINER" \
                            mysqladmin ping \
                            -h localhost \
                            -u root \
                            -proot_password \
                            --silent >/dev/null 2>&1; then

                            echo "MySQL is ready."
                            break
                        fi

                        if [ "$i" -eq 90 ]; then
                            echo "MySQL failed to become ready."
                            docker logs "$MYSQL_CONTAINER"
                            exit 1
                        fi

                        sleep 2
                    done
                '''
            }
        }

        stage('Start Laravel') {
            steps {
                sh '''
                    set -e

                    docker rm -f "$APP_CONTAINER" >/dev/null 2>&1 || true

                    docker run -d \
                        --name "$APP_CONTAINER" \
                        --network "$NETWORK" \
                        --publish "$APP_PORT:8000" \
                        -e APP_ENV=testing \
                        -e APP_DEBUG=true \
                        -e APP_KEY="base64:$(openssl rand -base64 32)" \
                        -e APP_URL="http://localhost:$APP_PORT" \
                        -e DB_CONNECTION=mysql \
                        -e DB_HOST=mysql \
                        -e DB_PORT=3306 \
                        -e DB_DATABASE=online_shop \
                        -e DB_USERNAME=online_shop \
                        -e DB_PASSWORD=online_shop_password \
                        online-shop-app-ci:latest

                    echo "=== Waiting for Laravel ==="

                    for i in $(seq 1 60); do
                        if docker run --rm \
                            --network "$NETWORK" \
                            curlimages/curl:latest \
                            -fsS --connect-timeout 10 --max-time 15 \
                            "http://$APP_CONTAINER:8000/up" \
                            >/dev/null 2>&1; then

                            echo "Laravel is ready."
                            break
                        fi

                        if [ "$i" -eq 60 ]; then
                            echo "Laravel failed to become ready."
                            docker logs "$APP_CONTAINER"
                            exit 1
                        fi

                        sleep 2
                    done

                    docker logs "$APP_CONTAINER"
                '''
            }
        }

        stage('Run Migrations') {
            steps {
                sh '''
                    set -e

                    docker exec "$APP_CONTAINER" \
                        php artisan migrate --force
                '''
            }
        }

        stage('Run Tests') {
            steps {
                sh '''
                    set -e

                    docker exec "$APP_CONTAINER" \
                        php artisan test
                '''
            }
        }

        stage('HTTP Health Check') {
            steps {
                sh '''
                    set -e

                    echo "=== Checking Laravel HTTP endpoint ==="

                    docker run --rm \
                        --network "$NETWORK" \
                        curlimages/curl:latest \
                        -fsS --connect-timeout 10 \
                        "http://$APP_CONTAINER:8000" \
                        >/dev/null

                    echo "Laravel HTTP health check passed."
                '''
            }
        }
    }

    post {
        always {
            sh '''
                echo "=== Jenkins cleanup ==="

                docker logs "$APP_CONTAINER" >/dev/null 2>&1 || true
                docker logs "$MYSQL_CONTAINER" >/dev/null 2>&1 || true

                docker rm -f "$APP_CONTAINER" >/dev/null 2>&1 || true
                docker rm -f "$MYSQL_CONTAINER" >/dev/null 2>&1 || true

                docker network rm "$NETWORK" >/dev/null 2>&1 || true
                docker volume rm "$MYSQL_VOLUME" >/dev/null 2>&1 || true

                echo "Cleanup complete."
            '''
        }
    }
}
