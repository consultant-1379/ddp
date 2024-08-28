#!/bin/sh

DIRNAME=`dirname $0`
LIBDIR="$DIRNAME/../../ksar";

# Setup the JVM
if [ "x$JAVA_HOME" != "x" ]; then
    JAVA="$JAVA_HOME/bin/java"
else
    JAVA="java"
fi

if [ ! -f "$LIBDIR/lib/kSar.jar" ] ; then
    echo "Unable to find kSar.jar - have you built it yet?"
    exit 1;
fi

JARS="iText-2.1.3.jar jcommon-1.0.16.jar jfreechart-1.0.13.jar jsch-0.1.41.jar swing-layout-1.0.3.jar"
for jar in $JARS ; do
    CLASSPATH="${CLASSPATH}:${LIBDIR}/lib/${jar}"
done
CLASSPATH="$LIBDIR/lib/kSar.jar:${CLASSPATH}"
export CLASSPATH

JAVA_OPT="-Xmx384m"

exec $JAVA $JAVA_OPT net.atomique.ksar.Main $@
