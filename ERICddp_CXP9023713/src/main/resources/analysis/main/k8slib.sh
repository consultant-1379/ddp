#!/bin/bash

writeJobYaml() {
    local JOB_NAME=$1
    local MAKESTATS_SELECTOR=$2
    local INSTANCE=$3
    local WORKER_IMAGE=$4
    local PRIVATE_IP=$5
    local CMD="$6"

    cat > /data/tmp/${JOB_NAME}.yml <<EOF
apiVersion: batch/v1
kind: Job
metadata:
  name: $JOB_NAME
spec:
  template:
    spec:
      nodeSelector:
          makestats: ${MAKESTATS_SELECTOR}
      restartPolicy: Never
      volumes:
EOF

    # Configure volumes - depends on where we're getting
    # the NFS share from
    if [ -r /data/tmp/nfs-pvc ] ; then
        cat >> /data/tmp/${JOB_NAME}.yml <<EOF
        - name: data
          persistentVolumeClaim:
            claimName: ${INSTANCE}-data
EOF
    else
        cat >> /data/tmp/${JOB_NAME}.yml <<EOF
        - name: stats
          nfs:
             server: "${PRIVATE_IP}"
             path: "/data/stats"
             readOnly: false
        - name: incr
          nfs:
            server: "${PRIVATE_IP}"
            path: "/data/tmp/incr"
            readOnly: false
        - name: ddp
          nfs:
            server: "${PRIVATE_IP}"
            path: "/data/ddp"
            readOnly: false
EOF
    fi

    if [ -r /etc/certs/db-srv-ca.cer ] ; then
        cat >> /data/tmp/${JOB_NAME}.yml <<EOF
        - name: tls-cert
          secret:
            secretName: ${INSTANCE}-db-statsadm
        - name: tls-ca
          secret:
            secretName: ${INSTANCE}-db-ca
EOF
    fi

    cat >> /data/tmp/${JOB_NAME}.yml <<EOF
      containers:
      - name: makestats
        image: ${WORKER_IMAGE}
        imagePullPolicy: IfNotPresent
        resources:
           requests:
              cpu: "2000m"
        command: [ ${CMD} ]
        volumeMounts:
EOF

    # Configure volumeMounths - depends on where we're getting
    # the NFS share from
    if [ -r /data/tmp/nfs-pvc ] ; then
        cat >> /data/tmp/${JOB_NAME}.yml <<EOF
        - mountPath: "/data/stats"
          name: data
          subPath: stats
        - mountPath: "/data/tmp/incr"
          name: data
          subPath: tmp/incr
        - mountPath: "/data/ddp"
          name: data
          subPath: ddp
EOF
    else
        cat >> /data/tmp/${JOB_NAME}.yml <<EOF
        - mountPath: "/data/stats"
          name: stats
        - mountPath: "/data/tmp/incr"
          name: incr
        - mountPath: "/data/ddp"
          name: ddp
EOF
    fi

    if [ -r /etc/certs/db-srv-ca.cer ] ; then
        cat >> /data/tmp/${JOB_NAME}.yml <<EOF
        - mountPath: "/etc/tls-cert"
          name: tls-cert
          readOnly: true
        - mountPath: "/etc/tls-ca"
          name: tls-ca
          readOnly: true
EOF
    fi
}
