<?php
foreach(acf_get_field_groups() as $g){ 
    $fields = acf_get_fields($g); 
    if(!$fields) continue; 
    foreach($fields as $f){ 
        if($f['type'] == 'select' && !isset($f['multiple'])){ 
            echo "Field {$f['name']} in group {$g['title']} is missing multiple\n"; 
        } 
        if(isset($f['sub_fields'])){
            foreach($f['sub_fields'] as $sf){
                if($sf['type'] == 'select' && !isset($sf['multiple'])){ 
                    echo "Subfield {$sf['name']} in field {$f['name']} in group {$g['title']} is missing multiple\n"; 
                } 
            }
        }
    } 
}

