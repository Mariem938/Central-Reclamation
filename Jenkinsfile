pipeline {
    agent any

    environment {
        JAVA_HOME = "C:\\Program Files\\Java\\jdk-21.0.10"
        PATH = "${JAVA_HOME}\\bin;${env.PATH}"

        IMAGE_NAME = "mariembit/central_reclamation"
        IMAGE_TAG = "${BUILD_NUMBER}"
    }

    stages {

        stage('Check SonarScanner Installation') {
            steps {
                script {
                    def scannerHome = tool 'MySonarScanner'

                    echo "SonarScanner should be installed at: ${scannerHome}"

                    bat "dir \"${scannerHome}\\bin\\sonar-scanner.bat\""

                    bat "\"${scannerHome}\\bin\\sonar-scanner.bat\" -v"
                }
            }
        }

        stage('Checkout from GitHub') {
            steps {
                git url: 'https://github.com/Mariem938/Central-Reclamation'
            }
        }

        stage('Analyse SonarQube') {
            steps {
                script {

                    def scannerHome = tool 'MySonarScanner'

                    withSonarQubeEnv('projet') {

                        withCredentials([
                            string(
                                credentialsId: 'sonarqube',
                                variable: 'TOKEN'
                            )
                        ]) {

                            bat "\"${scannerHome}\\bin\\sonar-scanner.bat\" " +
                                "-Dsonar.projectKey=node-projetcid " +
                                "-Dsonar.sources=. " +
                                "-Dsonar.login=%TOKEN% " +
                                "-Dsonar.projectVersion=1.0.0 " +
                                "-Dsonar.sourceEncoding=UTF-8"
                        }
                    }
                }
            }
        }

        stage('Build Docker Image') {
            steps {
                script {

                    def dockerImage = docker.build("${IMAGE_NAME}:${IMAGE_TAG}")

                }
            }
        }

        stage('Push Docker Image') {
            steps {

                withCredentials([
                    usernamePassword(
                        credentialsId: 'dockerhub-cred',
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASS'
                    )
                ]) {

                    bat 'docker login -u %DOCKER_USER% -p %DOCKER_PASS%'

                    bat "docker push ${IMAGE_NAME}:${IMAGE_TAG}"
                }
            }
        }

        stage('Deploy to Kubernetes') {
            steps {

                bat 'kubectl apply -f deployment.yaml'

            }
        }
    }

    post {

        success {
            echo 'Pipeline réussi'
        }

        failure {
            echo 'Pipeline échoué'
        }
    }
}