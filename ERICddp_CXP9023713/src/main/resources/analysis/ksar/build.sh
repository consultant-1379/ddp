#!/bin/bash

set -a

BASE_DIR=$(dirname $0)
LIBPATH=${BASE_DIR}/lib/
JARS="iText-2.1.3.jar jcommon-1.0.13.jar jfreechart-1.0.11.jar jsch-0.1.40.jar swing-layout-1.0.3.jar"

for jar in $JARS ; do
    CLASSPATH=${LIBPATH}/${jar}:${CLASSPATH}
done

# CIF build VOBs
#*******************************************************
TPP_DIR=/vobs/ossrc_3pp
CIF_3PP_DIR=${TPP_DIR}/cif_3pp
FREEWARE_DIR=${TPP_DIR}/freeware

# 3PP home directories
#*******************************************************
ANT_HOME=${FREEWARE_DIR}/ant
JAVA_HOME=${CIF_3PP_DIR}/java/jdk
PATH=${JAVA_HOME}/bin:$PATH

${ANT_HOME}/bin/ant --noconfig -emacs -buildfile ${BASE_DIR}/ant.xml -Dbasedir=${BASE_DIR} -Dmyclasspath=${CLASSPATH} $*
