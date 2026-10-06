<?php
// Per-instance grants for `simo1` (the read-only replica instance): depends on
// the shared roles package (which defines the roles) and holds only this
// instance's grants (on the `simo` schema it serves). {{dbname}} is filled by
// gen-service-accounts from the package's `dbname` (the schema).
return new \Ema\Config\PackageConfig(
    dependencies: [
        'roles-D0YRR7WII6V1XDZR',
    ],
);
