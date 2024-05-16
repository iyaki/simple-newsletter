# Simple Newsletter

## Deploy

Copy `compose-prod.yaml` and `.env` to the server and create folder `data/`

Service starts and updates with:

`docker compose up --wait --pull always`

Cron:

`15 * * * * curl https://simple-newsletter.com/send-newsletters/ >> /root/send-newsletters.log`


## Cleanup

```shell
docker system prune --all --force --volumes
docker image prune -f
```
