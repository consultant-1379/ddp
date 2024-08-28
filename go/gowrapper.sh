#!/bin/bash

ROOT_DIR=$(dirname $0)

if [ ! -d ${ROOT_DIR}/bin ] ; then
 mkdir ${ROOT_DIR}/bin 
fi

IMAGE=armdocker.rnd.ericsson.se/proj-adp-cicd-drop/bob-gobuilder.adp-base-os:4.36.0

if [ "$1" = "compile" ] ; then
    COMMIT_ID=$(git show --oneline -s | awk '{print $1}')
    echo ${ARGS}
    docker run --rm \
    --volume ${ROOT_DIR}:${ROOT_DIR} \
    --workdir ${ROOT_DIR} \
    --env GOBIN=${ROOT_DIR}/bin \
    $IMAGE \
    go install -v -ldflags="-X main.Commit=${COMMIT_ID} -extldflags=-static" ${ROOT_DIR}/...
else
    docker run --rm \
    --volume ${ROOT_DIR}:${ROOT_DIR} \
    --workdir ${ROOT_DIR} \
    --env GOBIN=${ROOT_DIR}/bin \
    $IMAGE \
    go "$@"
fi


