import json

filepath = r"C:\DEV\WC Plugins\My Plugins\HP-React-Widgets\acf-json\group_hp_funnel_config.json"

with open(filepath, 'r') as f:
    data = json.load(f)

def find_missing_select_fields(fields, path=""):
    missing_fields = []
    for field in fields:
        field_name = field.get('name', 'unnamed')
        current_path = f"{path}/{field_name}" if path else field_name
        
        if field.get('type') == 'select':
            if 'multiple' not in field:
                missing_fields.append(current_path)
        
        if 'sub_fields' in field:
            missing_fields.extend(find_missing_select_fields(field['sub_fields'], current_path))
            
    return missing_fields

missing = find_missing_select_fields(data['fields'])
print(f"Fields missing 'multiple': {missing}")

