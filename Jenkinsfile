 
pipeline {
    agent any
    
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
        
        stage('Checkout GitHub') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: 'master'
                    echo "Branche détectée : ${branchName}"
                    
                    checkout([
                        $class: 'GitSCM',
                        branches: [[name: "*/${branchName}"]],
                        userRemoteConfigs: [[
                            url: 'https://github.com/Mariem938/Central-Reclamation.git',
                            credentialsId: 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
                        ]]
                    ])
                }
            }
        }
        
        stage('Analyse SonarQube') {
            steps {
                script {
                    def scannerHome = tool 'MySonarScanner'
                    withSonarQubeEnv('projet') {
                        withCredentials([string(credentialsId: 'sonarqube', variable: 'TOKEN')]) {
                            bat "\"${scannerHome}\\bin\\sonar-scanner.bat\" " +
                                "-Dsonar.projectKey=central-reclamation " +
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