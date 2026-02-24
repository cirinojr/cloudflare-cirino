# Cloudflare Cirino

Plugin de limpeza automatizada de cache de borda do cloudflare.

## Como funciona?

O plugin utiliza um hook de savepost e toda vez que um conteúdo é salvo ou atualizado ele envia um "purge all" para o CF limpando o cache do domínio todo.


## Como usar?

Após ativar o plugin vá em Ferramentas> Cirino Cloudflare e adicione o Zone ID do domínio e salvar



