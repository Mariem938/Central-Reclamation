pipeline {
    agent any
    environment {
        JAVA_HOME = "C:\\Program Files\\Java\\jdk-21.0.10"
        PATH = "${JAVA_HOME}\\bin;${env.PATH}"
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
                        withCredentials([string(credentialsId: 'sonarqube', variable: 'TOKEN')]) {
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
    }
}