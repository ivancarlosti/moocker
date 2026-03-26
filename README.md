# Moocker

Um sistema de treinamento em Docker usando o Moodle.

## Como rodar localmente com o Docker Compose

Os arquivos necessários para rodar o projeto via Docker Compose estão na pasta `docker`.

1. Acesse o diretório `docker`
```bash
cd docker
```

2. (Opcional) Edite as variáveis no arquivo `.env` caso deseje alterar as portas ou as credenciais do banco.  
O arquivo padrão já vem com tudo pronto para um teste inicial na porta 8080.

3. Inicie os containers com o Docker Compose
```bash
docker-compose up -d
```

4. Acesse o Moodle no seu navegador: `http://localhost:8080` e prossiga com a etapa de instalação via web informando as credenciais definidas no `.env` referentes ao banco de dados:
   - **Database driver**: MariaDB
   - **Database host**: db
   - **Database name**: moodle (ou o configurado no DB_NAME)
   - **Database user**: moodleuser (ou o configurado no DB_USER)
   - **Database password**: moodlepass (ou o configurado no DB_PASSWORD)

> Note: Uma action no GitHub Packages gera novar builds da imagem Moodle sempre que existir uma nova versão estável, publicando na imagem `ghcr.io/ivancarlosti/moocker:latest`.

## Usando com um Reverse Proxy (ex: Nginx)

Para usar este projeto em um servidor com reverse proxy (como Nginx, Traefik ou similar), você pode fazê-lo em poucas etapas:

1. **Atualize o arquivo `.env`**:
   No arquivo `docker/.env`, preencha a variável `MOODLE_URL` com a sua URL pública:
   ```env
   MOODLE_URL=https://meumoodle.com.br
   ```

2. **Certifique-se do Port Binding Seguro**:
   O `docker-compose.yml` expõe o serviço na porta apenas para o `localhost` (`127.0.0.1:${MOODLE_PORT}:80`), ou seja, para acessá-lo publicamente, é obrigatório passar pelo Reverse Proxy que fará o repasse.

3. **Configure seu Reverse Proxy**:
   Configure seu Reverse Proxy para redirecionar o tráfego da porta 80/443 do seu domínio para a porta `localhost:8080` (onde o Moodle está rodando localmente no Docker).
   
   **Exemplo com Nginx:**
   ```nginx
   server {
       listen 80;
       server_name meumoodle.com.br;

       location / {
           proxy_pass http://127.0.0.1:8080;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```

4. **Instalação e SSL Proxy**:
   - Ao acessar `https://meumoodle.com.br` no navegador pela primeira vez, a instalação utilizará automaticamente seu `MOODLE_URL` do `.env` como URL principal.
   - Caso o frontend esteja utilizando HTTPS (o padrão de certificados TLS no Reverse Proxy), pode ser necessário avisar o Moodle no arquivo de configuração ao finalizar a instalação. Acesse o container do moodle e edite o arquivo (`docker exec -it moodle_app bash`, depois `nano /var/www/moodledata/config.php`) adicionando a seguinte linha se enfrentar loops de redirecionamentos:
     ```php
     $CFG->sslproxy = true;
     ```

## Usando um Banco de Dados MariaDB Externo

Caso você não queira utilizar o serviço `db` incluso no `docker-compose.yml` e prefira usar um banco de dados MariaDB externo (em outro servidor ou serviço na nuvem), siga estes passos:

1. **Crie o Banco de Dados Corretamente**:
   No seu MariaDB externo, é obrigatório criar o banco de dados utilizando a codificação `utf8mb4`. Execute a seguinte query no seu banco de dados:
   ```sql
   CREATE DATABASE moodle DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Edite as Variáveis de Ambiente**:
   No arquivo `docker/.env`, altere as configurações de conexão para apontar para o seu banco externo:
   ```env
   DB_NAME=moodle
   DB_USER=seu_usuario
   DB_PASSWORD=sua_senha
   ```

3. **Atualize o Host do Banco de Dados no Docker Compose**:
   No arquivo `docker/docker-compose.yml`, altere a variável fixa `MOODLE_DATABASE_HOST=db` para apontar para o IP ou domínio do seu servidor externo de banco de dados:
   ```yaml
   environment:
     - MOODLE_DATABASE_HOST=ip_do_seu_banco
     ...
   ```

4. **(Opcional) Remova o serviço local de banco de dados**:
   Para não subir um banco local desnecessário, você pode remover ou comentar toda a seção `db:` no arquivo `docker-compose.yml`, assim como a string correspondente em `depends_on:` no serviço `moodle`.