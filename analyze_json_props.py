import json
import os

filepath = r"C:\DEV\WC Plugins\My Plugins\HP-React-Widgets\acf-json\group_hp_funnel_config.json"
with open(filepath, 'r') as f:
    data = json.load(f)

field_types = {}

def analyze_fields(fields):
    for field in fields:
        ftype = field.get('type')
        if ftype not in field_types:
            field_types[ftype] = set()
        field_types[ftype].update(field.keys())
        
        if 'sub_fields' in field:
            analyze_fields(field['sub_fields'])
        if 'layouts' in field:
            for l in field['layouts']:
                analyze_fields(l.get('sub_fields', []))

analyze_fields(data['fields'])

for ftype, props in field_types.items():
    print(f"Type: {ftype}")
    print(f"  Props: {sorted(list(props))}")

