#!/usr/bin/env bash

# This script should be run on each server that needs to pull and install the latest version of docker

if [ -z "${DEPLOY_TARGET}" ]; then
    TARGET='stable'
else
    TARGET=${DEPLOY_TARGET}
fi

docker login -u aereusdev -p p7pfsGRe docker.aereusdev.com:5001
docker pull docker.aereusdev.com:5001/netric:${TARGET}

# Run the daemon with bin/netricd start-fg (start foreground)
docker stop netricd
docker rm netricd

docker run -P -d --restart=on-failure --name netricd \
    -e APPLICATION_ENV="production" \
    docker.aereusdev.com:5001/netric:${TARGET} /start-daemon.sh

# Optionally use syslog for the log driver
#docker run -P -d --restart=on-failure --name netricd \
#    -e APPLICATION_ENV="production" --log-driver=syslog --log-opt tag=netric-${TARGET} \
#    --log-opt syslog-facility=local2 docker.aereusdev.com:5001/netric:${TARGET} /start-daemon.sh

# Run setup in the background and it will die when finished
docker stop netricsetup
docker rm netricsetup
docker run -P -d --name netricsetup -e APPLICATION_ENV="production" \
    docker.aereusdev.com:5001/netric:${TARGET} /netric-setup.sh