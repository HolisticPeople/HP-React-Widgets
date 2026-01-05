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
            print(f"Skipping {filename} due to load error.")
            continue

    def fix_select_fields(fields):
        fixed_count = 0
        for field in fields:
            if field.get('type') == 'select':
                # Check for standard select properties
                props = {
                    'multiple': 0,
                    'allow_null': 0,
                    'ui': 0,
                    'ajax': 0,
                    'return_format': 'value',
                    'placeholder': '',
                    'create_options': 0,
                    'save_options': 0
                }
                added = False
                for k, v in props.items():
                    if k not in field:
                        field[k] = v
                        added = True
                if added:
                    fixed_count += 1
            
            if 'sub_fields' in field:
                fixed_count += fix_select_fields(field['sub_fields'])
            if 'layouts' in field: # For flexible content
                for layout in field['layouts']:
                    if 'sub_fields' in layout:
                        fixed_count += fix_select_fields(layout['sub_fields'])
                
        return fixed_count

    total_fixed = fix_select_fields(data.get('fields', []))
    if total_fixed > 0:
        with open(filepath, 'w') as f:
            json.dump(data, f, indent=4)
        print(f"Fixed {total_fixed} select fields in {filename}.")

