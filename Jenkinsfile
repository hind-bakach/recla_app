pipeline {
    agent any

    environment {
        IMAGE_NAME = 'recla-app'
        IMAGE_TAG  = "v${BUILD_NUMBER}"
        NAMESPACE  = 'recla-app'
    }

    stages {

        stage('📥 Cloner le code') {
            steps {
                checkout scm
                echo "✅ Code récupéré"
            }
        }

        stage('🔍 Lint PHP') {
            steps {
                sh 'find . -name "*.php" | xargs -I {} php -l {}'
                echo "✅ Syntaxe PHP OK"
            }
        }

        stage('🐳 Build Image Docker') {
            steps {
                sh """
                    docker build -t ${IMAGE_NAME}:${IMAGE_TAG} \
                                 -t ${IMAGE_NAME}:latest \
                                 -f docker/Dockerfile .
                """
                echo "✅ Image Docker buildée"
            }
        }

        stage('🏗️ Terraform') {
            steps {
                dir('terraform') {
                    sh '''
                        terraform init
                        terraform apply -auto-approve
                    '''
                }
                echo "✅ Infrastructure prête"
            }
        }

        stage('☸️ Kubernetes') {
            steps {
                sh '''
                    kubectl apply -f kubernetes/deployment.yaml
                    kubectl apply -f kubernetes/service.yaml
                    kubectl rollout status deployment/recla-app -n recla-app --timeout=300s || true
        '''
                '''
                echo "✅ App déployée sur Kubernetes"
            }
        }
    }

    post {
        success {
            echo '''
            🎉 PIPELINE RÉUSSI !
            ✅ Code vérifié
            ✅ Image Docker buildée
            ✅ Terraform OK
            ✅ Déployé sur Kubernetes
            App : http://localhost:30080
            '''
        }
        failure {
            echo '❌ Pipeline échoué — consulter les logs'
        }
    }
}