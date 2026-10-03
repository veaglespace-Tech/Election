import os
import re
import sys

def main():
    filename = 'vps_seed_data.sql'
    if not os.path.exists(filename):
        print("File not found!")
        return

    with open(filename, 'rb') as f:
        raw_data = f.read()

    print(f"Original size: {len(raw_data)} bytes")
    null_count = raw_data.count(b'\x00')
    print(f"Null bytes found: {null_count}")

    # Count inserts before any transformation
    # We can try to decode gracefully just for counting
    try:
        if raw_data.startswith(b'\xff\xfe') or raw_data.startswith(b'\xfe\xff'):
            temp_text = raw_data.decode('utf-16')
        else:
            # Maybe it's corrupted utf-8 with nulls
            temp_text = raw_data.replace(b'\x00', b'').decode('utf-8', errors='replace')
    except Exception as e:
        print("Error decoding for count:", e)
        temp_text = raw_data.decode('latin1')

    original_inserts = len(re.findall(r'(?i)INSERT\s+INTO\s+`?electors`?', temp_text))
    print(f"Original INSERT count: {original_inserts}")

    # Now clean the data
    # Requirement: Remove all NULL bytes (\x00)
    cleaned_binary = raw_data.replace(b'\x00', b'')

    # Requirement: Standard UTF-8, no BOM
    if cleaned_binary.startswith(b'\xef\xbb\xbf'):
        cleaned_binary = cleaned_binary[3:]

    # Decode to string to normalize line endings and ensure it's valid UTF-8
    try:
        text = cleaned_binary.decode('utf-8')
    except UnicodeDecodeError:
        print("Warning: Contains invalid UTF-8 sequences. Using replacement character.")
        text = cleaned_binary.decode('utf-8', errors='replace')

    # Normalize line endings to LF (standard) or CRLF? "CRLF/UTF-8 BOM issue असल्यास standard UTF-8 मध्ये normalize करा."
    # Let's keep \n as standard.
    text = text.replace('\r\n', '\n').replace('\r', '\n')

    # Count inserts after
    final_inserts = len(re.findall(r'(?i)INSERT\s+INTO\s+`?electors`?', text))
    print(f"Final INSERT count: {final_inserts}")

    # Write back
    final_binary = text.encode('utf-8')
    with open(filename, 'wb') as f:
        f.write(final_binary)

    print(f"Final size: {len(final_binary)} bytes")
    print(f"Final null bytes: {final_binary.count(b'\x00')}")
    print("Done.")

if __name__ == '__main__':
    main()
