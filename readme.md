# FERRAMENTA DE REDO DE LOG<hr>

## Começando
### 1. Dependências

Para executar o projeto, você precisa ter o seguinte instalado (preceisa `php >= 8.1`).:

- [Git](https://git-scm.com);
- [PHP 8.1](https://www.php.net/downloads);
- [Composer](https://getcomposer.org/download/);

> _IMPORTANTE:_ se sua distribuição linux não tem PHP 8.1 disponível, rode `sudo add-apt-repository ppa:ondrej/php` antes de começar.

Você precisa de várias extensões PHP instaladas também:

```
sudo apt-get update
sudo apt install php8.1-sqlite3 php8.1-mbstring 
```

### 2. Configuração

Feito a instalação das dependências, é necessário obter uma cópia do projeto. A forma recomendada é clonar o repositório para a sua máquina.

Para isso, rode:

```
git clone --recurse-submodules https://github.com/vieir4ndo/log-redo-tool && cd log-redo-tool
```

Isso criará e trocará para a pasta `log-redo-tool` com o código do projeto.

#### 2.1 COMPOSER

Instale as dependências do projeto usando o comando abaixo:

```
composer install
```

#### 2.2 Banco de Dados

O banco de dados utilizado é o sqlite e ele é gerado automaticamente quando o script é executado. 

> _IMPORTANTE:_ Caso o arquivo database.sqlite já exista na raíz do projeto, então não serão criadas as tabelas default utilizadas pelo script.


### 3. Utilizacão

#### 3.1 Rodando o projeto

Depois de seguir todos os passos de instalação, execute o script 'log-redo-tool.php':

Executando o comando abaixo serão utilizados como entradas os arquivos 'metadata.json' e 'log' que estão presentes na raíz do projeto
```
php log-redo-tool.php
```

Caso deseje utilizar entradas diferentes dessas, apenas informe o diretório para as mesmas como no exemplo abaixo:
```
php log-redo-tool.php --metada=/c/teste/teste.json --log=/c/teste/teste.json
```

