<?php

if (!isset($submission->transactions[0])) {
    return '';
}

$id = $submission->transactions[0]->charge_id;
echo $id;
