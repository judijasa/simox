<?php
// Per-database grants for `simo0` (the writable primary): depends on the
// shared roles package (which defines the roles) and holds only this
// database's grants. {{dbname}} is filled by gen-service-accounts from the
// target database name.
return new \Ema\Config\PackageConfig(
    dependencies: [
        'roles-D0YRR7WII6V1XDZR',
    ],
);
