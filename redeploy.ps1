# Variables
$imageName = "central-reclamation-backend:latest"
$deploymentName = "central-reclamation-backend"

Write-Host "Activation de l'environnement Docker de Minikube..."
minikube -p minikube docker-env | Invoke-Expression

Write-Host "Construction de l'image Docker du backend..."
docker build -t $imageName .

Write-Host "Redémarrage du déploiement Kubernetes..."
kubectl rollout restart deployment $deploymentName

Write-Host "Attente du redémarrage des pods..."
Start-Sleep -Seconds 5

Write-Host "État des pods :"
kubectl get pods
``