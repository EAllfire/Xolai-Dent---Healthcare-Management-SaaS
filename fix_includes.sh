#!/bin/bash

# Fix all relative require_once paths in citas folder to use __DIR__
cd /Applications/MAMP/htdocs/agenda/citas

for file in *.php; do
    if [ -f "$file" ]; then
        # Replace require_once '../includes/ with require_once __DIR__ . '/../includes/
        sed -i '' "s|require_once '../includes/|require_once __DIR__ . '/../includes/|g" "$file"
        echo "Fixed: $file"
    fi
done

echo "All includes in /citas/ have been fixed!"
