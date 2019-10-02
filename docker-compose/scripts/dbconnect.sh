#!/bin/bash
# This connects to the development database. The user and database name are in docker-compose.yml
docker-compose exec kitsdb mysql -h kitsdb -P 3306 -u wordpress -pwordpress wordpress
