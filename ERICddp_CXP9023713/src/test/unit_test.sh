#!/bin/bash

ROOT_DIR=$(dirname $0)
ROOT_DIR=$(cd ${ROOT_DIR} ; cd ../../.. ; pwd)
FILE=$1

prove -v -I${ROOT_DIR}/ERICddp_CXP9023713/src/main/resources/analysis/common -r ${ROOT_DIR}/ERICddp_CXP9023713/src/test/perl/${FILE}
PROVE_RESULT=$?

exit ${PROVE_RESULT}
