#!/bin/bash

# To be exec after remove_outdated.sh

##### BEGIN BLOCK: CRON
#export  PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/snap/bin
#DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
#cd $DIR
##### END BLOCK: CRON

. admin_config.sh

mysql -u "${USER}" -p"${PASSWORD}" "${DATABASE}" <<EOF

DROP TABLE IF EXISTS Jobs;
CREATE TABLE Jobs LIKE Jobs_tmp;
INSERT INTO Jobs SELECT * FROM Jobs_tmp;
EOF
