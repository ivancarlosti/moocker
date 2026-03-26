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