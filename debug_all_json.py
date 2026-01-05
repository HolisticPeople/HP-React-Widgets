import os

folder = r"C:\DEV\WC Plugins\My Plugins\HP-React-Widgets\acf-json"

for filename in os.listdir(folder):
    if not filename.endswith('.json'):
        continue
    
    filepath = os.path.join(folder, filename)
    with open(filepath, 'r') as f:
        lines = f.readlines()

    found_any = False
    for i, line in enumerate(lines):
        if '"type": "select"' in line:
            found_multiple = False
            field_name = "unknown"
            for j in range(max(0, i-10), min(len(lines), i+100)):
                if '"name":' in lines[j]:
                    field_name = lines[j].strip()
                if '"multiple":' in lines[j]:
                    found_multiple = True
                    break
            if not found_multiple:
                print(f"MISSING 'multiple' in {filename} at line {i+1} for field {field_name}")
                found_any = True

