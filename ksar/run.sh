#!/bin/sh

DIRNAME=`dirname $0`

# Setup the JVM
if [ "x$JAVA_HOME" != "x" ]; then
    JAVA="$JAVA_HOME/bin/java"
else
    JAVA="java"
fi

if [ ! -f "$DIRNAME/lib/kSar.jar" ] ; then
    echo "Unable to find kSar.jar - have you built it yet?"
    exit 1;
fi

JARS="iText-2.1.3.jar jcommon-1.0.13.jar jfreechart-1.0.11.jar jsch-0.1.40.jar swing-layout-1.0.3.jar"
for jar in $JARS ; do
    CLASSPATH="${CLASSPATH}:${DIRNAME}/lib/${jar}"
done
CLASSPATH="$DIRNAME/lib/kSar.jar:${CLASSPATH}"
export CLASSPATH
exec $JAVA $JAVA_OPT net.atomique.ksar.Main $@
