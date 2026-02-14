# 🚀 Como colocar o Sistema Financeiro Online no Render.com

O sistema já está configurado para rodar no Render. Siga os passos abaixo:

## 1. Banco de Dados (MySQL)
O Render não oferece MySQL gerenciado gratuitamente. Você tem duas opções:
1. **Opção A (Recomendada):** Usar um banco de dados externo gratuito (ex: **Aiven**, **PlanetScale** ou **Clever Cloud**).
2. **Opção B:** Criar um serviço MySQL no próprio Render (mas os dados podem ser perdidos na versão gratuita se o serviço reiniciar).

**Passos (Opção A):**
1. Crie uma conta no [Aiven](https://aiven.io/) ou [Clever Cloud](https://www.clever-cloud.com/).
2. Crie um banco de dados **MySQL**.
3. Copie as credenciais: `Host`, `Database Name`, `User`, `Password`, `Port`.
4. Use uma ferramenta como **DBeaver** ou **HeidiSQL** no seu PC para conectar nesse banco remoto e rodar o script `database.sql` para criar as tabelas.

## 2. Código (GitHub)
1. Crie um repositório no **GitHub**.
2. Envie todos os arquivos da pasta `financeiro` para lá.

## 3. Render (Web Service)
1. Crie uma conta no [Render.com](https://render.com/).
2. Clique em **New +** -> **Web Service**.
3. Conecte sua conta do GitHub e selecione o repositório que você criou.
4. Dê um nome para o serviço (ex: `meu-financeiro`).
5. **Runtime:** Selecione `Docker`.
6. Role até a seção **Environment Variables** e adicione:
   - `DB_HOST`: (O host do seu banco de dados, ex: `mysql-services.aivencloud.com`)
   - `DB_NAME`: (O nome do banco, ex: `defaultdb`)
   - `DB_USER`: (Seu usuário do banco)
   - `DB_PASS`: (Sua senha do banco)
   - `PORT`: `80`

7. Clique em **Create Web Service**.

O Render vai ler o arquivo `Dockerfile`, instalar o PHP/Apache e conectar no seu banco de dados. Em alguns minutos seu site estará online! 🌐
