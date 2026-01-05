import json

filepath = r"C:\DEV\WC Plugins\My Plugins\HP-React-Widgets\acf-json\group_hp_funnel_offers.json"

with open(filepath, 'r') as f:
    lines = f.readlines()

def find_missing():
    for i, line in enumerate(lines):
        if '"type": "select"' in line:
            # Check next 30 lines for 'multiple'
            found_multiple = False
            field_name = "unknown"
            for j in range(max(0, i-10), min(len(lines), i+30)):
                if '"name":' in lines[j]:
                    field_name = lines[j].strip()
                if '"multiple":' in lines[j]:
                    found_multiple = True
                    break
            if not found_multiple:
                print(f"MISSING 'multiple' at line {i+1} for field {field_name}")

find_missing()

