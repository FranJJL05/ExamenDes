# GUÍA MAESTRA DE DESPLIEGUE - EXAMEN DAW (AWS + Docker + SSL + CI/CD)

Esta es la **Guía Completa**. Sigue estos pasos uno a uno sin saltarte nada y tendrás un 10.
El objetivo es desplegar una aplicación segura (HTTPS) con dominio propio en AWS usando Docker.

---

## 📌 ÍNDICE
1.  **Requisitos Previos (Tu PC)**
2.  **Entender el Proyecto**
3.  **FASE 1: Infraestructura AWS (Consola)**
4.  **FASE 2: Configurar Dominio (Ionos)**
5.  **FASE 3: Preparar el Servidor (SSH)**
6.  **FASE 4: Certificados SSL Reales (Certbot)**
7.  **FASE 5: Modificar Código y Desplegar**
8.  **FASE 6: Automatización (GitHub Actions)**
9.  **Anexo: Solución de Problemas**

---

## 1. REQUISITOS PREVIOS (En tu PC)
Antes de nada, asegúrate de tener:
*   **Git Bash** (si usas Windows): Es la terminal que usaremos.
*   **Archivo `labsuser.pem`**: Tu llave para entrar al servidor.
*   **Código Fuente**: La carpeta del proyecto con `docker-compose.yml`, `backend`, `frontend`, etc.

---

## 2. ENTENDER EL PROYECTO
No es magia, son archivos:
*   **`docker-compose.yml`**: Define 2 servicios (`web` con Nginx y `app` con PHP).
*   **`nginx/nginx.conf`**: Configuración del servidor web.
*   **`backend/`**: Código PHP y base de datos (`todos.json`).
*   **`frontend/`**: Web HTML/JS.

---

## 3. FASE 1: INFRAESTRUCTURA AWS (Consola)

### 3.1. Lanzar Instancia EC2
1.  Entra en AWS -> **EC2** -> **Lanzar instancias**.
2.  **Nombre**: `ExamenDAW`.
3.  **AMI**: `Ubuntu Server 22.04 LTS`.
4.  **Tipo**: `t2.micro` (Capa gratuita).
5.  **Par de claves**: Selecciona `vockey`.
6.  **Configuración de red (Security Group)**:
    *   Dale a "Crear grupo de seguridad".
    *   Marca permitir tráfico SSH (Puerto 22).
    *   Marca permitir tráfico HTTP (Puerto 80).
    *   Marca permitir tráfico HTTPS (Puerto 443).
7.  Pulsa **Lanzar instancia**.

### 3.2. IP Elástica (¡OBLIGATORIO!)
Para que la IP no cambie si apagamos la máquina.
1.  En el menú izquierdo: **Red y seguridad** -> **Direcciones IP elásticas**.
2.  Botón naranja **Asignar dirección IP elástica** -> **Asignar**.
3.  Selecciona la IP creada -> **Acciones** -> **Asociar dirección IP elástica**.
4.  Elige tu instancia en "Instancia" y dale a **Asociar**.
5.  **COPIA ESTA IP**. A partir de ahora es "TU IP".

---

## 4. FASE 2: CONFIGURAR DOMINIO (IONOS)
1.  Entra en Ionos -> **Dominios & SSL**.
2.  Busca tu dominio y ve a **DNS**.
3.  Añade un **Registro A**:
    *   **Nombre de host**: `@` (o déjalo vacío).
    *   **Apunta a**: Pega tu **IP Elástica**.
4.  Guarda. (Puede tardar unos minutos en propagarse).

---

## 5. FASE 3: PREPARAR EL SERVIDOR (SSH)

Ahora nos conectamos al servidor para instalar Docker.

### 5.1. Permisos de la clave (Solo Windows/PowerShell)
Si estás en Windows, abre PowerShell en la carpeta del proyecto y ejecuta esto UNA VEZ para que la clave funcione:
```powershell
icacls labsuser.pem /reset
icacls labsuser.pem /grant:r "$($env:USERNAME):(R)"
icacls labsuser.pem /inheritance:r
```

### 5.2. Conexión SSH
Usa tu IP Elástica:
```bash
ssh -i "labsuser.pem" ubuntu@TU-IP-ELASTICA
```
*(Si te pregunta "Are you sure...", escribe `yes`).*

### 5.3. Instalar Docker y Herramientas
Ya dentro de la máquina ubuntu, ejecuta bloque a bloque:

```bash
# 1. Actualizar
sudo apt update

# 2. Instalar Docker y Certbot (para SSL)
sudo apt install docker.io docker-compose certbot -y
# (Si te sale una pantalla rosa preguntando por reiniciar servicios, simplemente pulsa ENTER)

# 3. Dar permisos a tu usuario para usar Docker sin sudo
sudo usermod -aG docker $USER
```
**IMPORTANTE**: Ahora escribe `exit` para salir y vuelve a conectarte por SSH para que se apliquen los permisos.

---

## 6. FASE 4: CERTIFICADOS SSL REALES (CERTBOT)
Con Docker apagado y el puerto 80 libre, pediremos el certificado a Let's Encrypt.

Conéctate de nuevo y ejecuta:
```bash
# CAMBIA 'midominio.com' POR TU DOMINIO REAL DE IONOS
sudo certbot certonly --standalone -d midominio.com
```
*   Te pedirá un email (pon cualquiera).
*   Acepta los términos (`Y`).
*   Si sale "Congratulations", ¡ya tienes SSL! 🎉

---

## 7. FASE 5: MODIFICAR CÓDIGO Y DESPLEGAR

### 7.1. Configurar Nginx (En tu PC Local)
1.  Abre el archivo `nginx/nginx.conf` de tu proyecto.
2.  Busca donde pone `server_name` y cámbialo por tu dominio real.
3.  Busca donde pone `ssl_certificate` y asegúrate de que apunta a la ruta de TU dominio:
    `/etc/letsencrypt/live/midominio.com/fullchain.pem`

### 7.2. Subir el código
Desde tu PC (Git Bash), sube la carpeta `proyecto` a la EC2:
```bash
# Asegúrate de estar en la carpeta donde está labsuser.pem
scp -i "labsuser.pem" -r . ubuntu@TU-IP-ELASTICA:~/proyecto
```

### 7.3. Desplegar (En la EC2)
Vuelve a la terminal SSH de la EC2:
```bash
cd proyecto

# Ajustar permisos (CRÍTICO para que funcione la Base de Datos y Web)
sudo chmod -R 755 frontend
sudo chmod -R 777 backend

# ¡Levantar el proyecto!
docker-compose up -d --build
```
Entra en `https://midominio.com`. **¡Deberías ver tu App segura!**

---

## 8. FASE 6: AUTOMATIZACIÓN (GITHUB ACTIONS)
Para que se actualice solo y arregle permisos automáticamente.

1.  Sube tu código a GitHub.
2.  En el repo, ve a **Settings** -> **Secrets and variables** -> **Actions**.
3.  Crea estos **Repository Secrets**:
    *   `EC2_HOST`: Tu dominio (ej. `midominio.com`).
    *   `EC2_USER`: `ubuntu`.
    *   `EC2_SSH_KEY`: Copia y pega TODO el texto de `labsuser.pem`.

¡Prueba a cambiar algo en el HTML, haz `git push` y mira cómo la web cambia sola!

---

## 9. ANEXO: SOLUCIÓN DE PROBLEMAS

*   **Error: "Permission denied (publickey)"**:
    *   En Windows, revisa los permisos de `labsuser.pem` (Paso 5.1).
    *   Asegúrate de usar el usuario `ubuntu`.

*   **Error: "403 Forbidden" al guardar tareas**:
    *   Faltan permisos en la carpeta backend. Ejecuta en la EC2: `sudo chmod -R 777 backend`. (El GitHub Action ya lo hace solo).

*   **Error: Docker falla al actualizar (KeyError, ContainerConfig, etc)**:
    *   A veces Docker se lía. La solución definitiva (Opción Nuclear) es borrar todos los contenedores y arrancar de cero:
        ```bash
        sudo docker rm -f $(sudo docker ps -aq)
        sudo docker-compose up -d --build
        ```

*   **Certbot falla**:
    *   Asegúrate de que la IP en Ionos es la correcta.
    *   Asegúrate de que Docker está parado (`docker-compose down`) antes de ejecutar certbot (necesita el puerto 80 libre).
