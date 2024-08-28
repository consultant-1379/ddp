#!/bin/bash

DIR=$1

HELM=no

#!/bin/bash

for DDP in ddp ddp2 ddpenm1 ddpenm2 ddpenm3 ddpenm5 ddpenm6 ddpenm7 ddpi ddpeo ; do
    for ONE in $(find ${DIR} -name '*.json') ; do
        NAME=$(basename ${ONE} .json)
        FILE_NAME=configmap-${DDP}-${NAME}.yaml
        cat > configmap-${DDP}-${NAME}.yaml <<EOF
apiVersion: v1
kind: ConfigMap
metadata:
    name: ${DDP}-${NAME}
    labels:
        ddp_dashboard: "1"
    annotations:
        k8s-sidecar-target-directory: "/var/lib/grafana/dashboards/${DDP}"
data:
  ${NAME}.json: |
EOF

        if [ "${HELM}" = "yes" ] ; then
            echo "{{- \$.Files.Get "dashboards/${DDP}-${NAME}.json"  | nindent 4 -}}" >> configmap-${DDP}-${NAME}.yaml
            cat "${ONE}" | sed -e 's/^/    /' -e "s/DATA_SOURCE_NAME/${DDP}/" -e "s/DB_UID_PREIX/${DDP}/" >> ../dashboards/${DDP}-${NAME}.json
        else
            cat "${ONE}" | sed -e 's/^/    /' -e "s/DATA_SOURCE_NAME/${DDP}/" -e "s/DB_UID_PREIX/${DDP}/" >> configmap-${DDP}-${NAME}.yaml
        fi
    done
done
