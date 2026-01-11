import json
import os

folder = r"C:\DEV\WC Plugins\My Plugins\HP-React-Widgets\acf-json"

# Common properties that almost every field should have
COMMON_PROPS = {
    'aria-label': '',
    'class': '',
    'id': '',
    'instructions': '',
    'required': 0,
    'conditional_logic': 0,
    'wrapper': {'width': '', 'class': '', 'id': ''}
}

TYPE_PROPS = {
    'text': {
        'default_value': '',
        'placeholder': '',
        'prepend': '',
        'append': '',
        'maxlength': '',
        'readonly': 0
    },
    'textarea': {
        'default_value': '',
        'placeholder': '',
        'maxlength': '',
        'rows': '',
        'new_lines': ''
    },
    'number': {
        'default_value': '',
        'placeholder': '',
        'prepend': '',
        'append': '',
        'min': '',
        'max': '',
        'step': '',
        'readonly': 0
    },
    'url': {
        'default_value': '',
        'placeholder': ''
    },
    'true_false': {
        'default_value': 0,
        'message': '',
        'ui': 0,
        'ui_on_text': '',
        'ui_off_text': ''
    },
    'select': {
        'multiple': 0,
        'allow_null': 0,
        'ui': 0,
        'ajax': 0,
        'return_format': 'value',
        'placeholder': '',
        'create_options': 0,
        'save_options': 0
    },
    'color_picker': {
        'default_value': '',
        'enable_opacity': 0,
        'return_format': 'string'
    },
    'wysiwyg': {
        'default_value': '',
        'tabs': 'all',
        'toolbar': 'full',
        'media_upload': 1,
        'delay': 0
    },
    'image': {
        'return_format': 'url',
        'preview_size': 'medium',
        'library': 'all',
        'min_width': 0,
        'min_height': 0,
        'min_size': 0,
        'max_width': 0,
        'max_height': 0,
        'max_size': 0,
        'mime_types': ''
    },
    'repeater': {
        'collapsed': '',
        'min': 0,
        'max': 0,
        'layout': 'table',
        'button_label': ''
    },
    'tab': {
        'placement': 'top',
        'endpoint': 0
    }
}

def fix_fields(fields):
    fixed_count = 0
    for field in fields:
        ftype = field.get('type')
        
        # Add common props
        for k, v in COMMON_PROPS.items():
            if k not in field:
                field[k] = v
                fixed_count += 1
        
        # Add type-specific props
        if ftype in TYPE_PROPS:
            for k, v in TYPE_PROPS[ftype].items():
                if k not in field:
                    field[k] = v
                    fixed_count += 1
        
        # Recurse
        if 'sub_fields' in field:
            fixed_count += fix_fields(field['sub_fields'])
        if 'layouts' in field:
            for layout in field['layouts']:
                if 'sub_fields' in layout:
                    fixed_count += fix_fields(layout['sub_fields'])
                    
    return fixed_count

for filename in os.listdir(folder):
    if not filename.endswith('.json'):
        continue
    
    filepath = os.path.join(folder, filename)
    with open(filepath, 'r') as f:
        try:
            data = json.load(f)
        except:
            continue

    total_fixed = fix_fields(data.get('fields', []))
    if total_fixed > 0:
        with open(filepath, 'w') as f:
            json.dump(data, f, indent=4)
        print(f"Fixed {total_fixed} properties in {filename}.")





