#!/bin/bash

ROOT_DIR=$(dirname $0)
VER=$1

CHART_DIR=$(date +/tmp/db_%s)
mkdir ${CHART_DIR}
cd ${CHART_DIR}

helm create ddp_dashboards
cd  ddp_dashboards
rm values.yaml

mkdir dashboards

cd templates
rm -rf *

bash ${ROOT_DIR}/genDbConfigMaps.sh ${ROOT_DIR}/../dashboards/
cd ../..

cd ${ROOT_DIR}
helm package ${CHART_DIR}/ddp_dashboards --app-version 1.0.${VER} --version 1.0.${VER}
#/bin/rm -rf ${CHART_DIR}
