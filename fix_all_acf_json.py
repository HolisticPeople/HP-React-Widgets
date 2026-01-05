import json
import os

folder = r"C:\DEV\WC Plugins\My Plugins\HP-React-Widgets\acf-json"

for filename in os.listdir(folder):
    if not filename.endswith('.json'):
        continue
    
    filepath = os.path.join(folder, filename)
    with open(filepath, 'r') as f:
        try:
            data = json.load(f)
        except:
            continue

    def fix_select_fields(fields):
        fixed_count = 0
        for field in fields:
            if field.get('type') == 'select':
                if 'multiple' not in field:
                    field.setdefault('multiple', 0)
                    field.setdefault('allow_null', 0)
                    field.setdefault('ui', 0)
                    field.setdefault('ajax', 0)
                    field.setdefault('return_format', 'value')
                    field.setdefault('placeholder', '')
                    field.setdefault('create_options', 0)
                    field.setdefault('save_options', 0)
                    fixed_count += 1
            
            if 'sub_fields' in field:
                fixed_count += fix_select_fields(field['sub_fields'])
                
        return fixed_count

    total_fixed = fix_select_fields(data['fields'])
    if total_fixed > 0:
        with open(filepath, 'w') as f:
            json.dump(data, f, indent=4)
        print(f"Fixed {total_fixed} select fields in {filename}.")

