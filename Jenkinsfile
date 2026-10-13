pipeline {
    agent any

    environment {
        COMPOSER_ALLOW_SUPERUSER = 1
        COMPOSER_NO_INTERACTION = 1
        DB_CONNECTION = 'sqlite'
        DB_DATABASE = ':memory:'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Install Dependencies') {
            steps {
                sh 'composer install --prefer-dist --no-progress --optimize-autoloader'
            }
        }

        stage('Environment Setup') {
            steps {
                sh 'cp .env.example .env || true'
                sh 'php artisan key:generate'
            }
        }

        stage('Database Migration') {
            steps {
                sh 'php artisan migrate --force --seed'
            }
        }

        stage('Code Quality') {
            parallel {
                stage('PHP Lint (Pint)') {
                    steps {
                        sh './vendor/bin/pint --test'
                    }
                }
                stage('Static Analysis (PHPStan)') {
                    steps {
                        sh 'composer require --dev phpstan/phpstan ^1.10 || true'
                        sh './vendor/bin/phpstan analyse --memory-limit=512M || true'
                    }
                }
            }
        }

        stage('Tests') {
            steps {
                sh 'php artisan test --parallel'
            }
            post {
                always {
                    junit 'storage/logs/junit.xml'
                }
            }
        }

        stage('Build Frontend') {
            when {
                expression { return fileExists('package.json') }
            }
            steps {
                sh 'npm ci'
                sh 'npm run build'
            }
        }

        stage('Security Audit') {
            steps {
                sh 'composer audit --no-dev || true'
                sh 'npm audit --production || true'
            }
        }
    }

    post {
        success {
            echo 'Pipeline succeeded!'
            // Deploy stage would go here
            // sh './deploy.sh'
        }
        failure {
            echo 'Pipeline failed!'
            // Notification: Slack, Email, etc.
        }
        always {
            cleanWs()
        }
    }
}