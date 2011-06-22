<?php
$sphinx_path = elgg_get_data_path() . 'sphinx';

mkdir("$sphinx_path/indexes", '0700', true);
mkdir("$sphinx_path/log", '0700');

sphinx_write_conf();
