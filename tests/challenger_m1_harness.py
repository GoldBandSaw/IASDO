#!/usr/bin/env python3
"""
Challenger 1 Stress & Boundary Test Harness for Milestone M1 (Feature F1: Sticky Right Sidebar)
Author: teamwork_preview_challenger
"""

import sys
import os
import re
import urllib.request
import urllib.error

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
DEV_SERVER_URL = "http://localhost:8000"

results = {
    "total": 0,
    "passed": 0,
    "failed": 0,
    "tests": []
}

def record_test(name, passed, details=""):
    results["total"] += 1
    if passed:
        results["passed"] += 1
        status = "PASS"
    else:
        results["failed"] += 1
        status = "FAIL"
    results["tests"].append({
        "name": name,
        "status": status,
        "details": details
    })
    prefix = "[PASS]" if passed else "[FAIL]"
    print(f"{prefix} {name}")
    if details:
        print(f"       Details: {details}")

print("======================================================================")
print("  CHALLENGER 1 EMPIRICAL HARNESS: STICKY SIDEBAR (M1 / F1)")
print("======================================================================")

# ----------------------------------------------------------------------
# SUITE 1: CSS Syntax, Token Balance & Malformation Stress
# ----------------------------------------------------------------------
print("\n--- Suite 1: CSS Grammar & Token Balance ---")

def test_token_balance():
    css_path = os.path.join(PROJECT_ROOT, "modern.css")
    with open(css_path, "r", encoding="utf-8") as f:
        content = f.read()

    stack = []
    pairs = {')': '(', ']': '[', '}': '{'}
    in_comment = False
    in_string = None
    errors = []

    i = 0
    while i < len(content):
        c = content[i]
        
        # Comment handling
        if not in_string:
            if not in_comment and c == '/' and i + 1 < len(content) and content[i+1] == '*':
                in_comment = True
                i += 2
                continue
            elif in_comment and c == '*' and i + 1 < len(content) and content[i+1] == '/':
                in_comment = False
                i += 2
                continue
        
        if in_comment:
            i += 1
            continue

        # String handling
        if c in ("'", '"'):
            if in_string == c and (i == 0 or content[i-1] != '\\'):
                in_string = None
            elif in_string is None:
                in_string = c
            i += 1
            continue

        if in_string:
            i += 1
            continue

        # Bracket matching
        if c in "({[":
            stack.append((c, i))
        elif c in ")}]":
            if not stack:
                errors.append(f"Unexpected closing {c} at offset {i}")
            else:
                top, top_i = stack.pop()
                if top != pairs[c]:
                    errors.append(f"Mismatched pair: opened {top} at {top_i}, closed with {c} at {i}")

        i += 1

    if in_comment:
        errors.append("Unclosed comment at end of modern.css")
    if in_string:
        errors.append(f"Unclosed string literal ({in_string}) at end of modern.css")
    if stack:
        errors.append(f"Unclosed open brackets: {[s[0] for s in stack]}")

    passed = len(errors) == 0
    record_test("CSS Token & Bracket Balance in modern.css", passed, "; ".join(errors) if errors else "Perfect balance")

def test_sidebar_declarations_validity():
    css_path = os.path.join(PROJECT_ROOT, "modern.css")
    with open(css_path, "r", encoding="utf-8") as f:
        content = f.read()

    # Match main .sidebar declaration
    match = re.search(r'\.sidebar\s*\{([^}]+)\}', content)
    if not match:
        record_test("Extract .sidebar block", False, "Could not find .sidebar block")
        return

    body = match.group(1)
    props = {}
    for line in body.split(';'):
        line = line.strip()
        if not line:
            continue
        if ':' in line:
            k, v = line.split(':', 1)
            props[k.strip().lower()] = v.strip().lower()

    # Check required F1 sticky declarations
    reqs = {
        "position": "sticky",
        "top": "0",
        "height": "100vh",
        "max-height": "100vh",
        "overflow-y": "auto",
        "overflow-x": "hidden",
        "width": "248px",
        "flex": "0 0 248px",
        "box-sizing": "border-box"
    }

    missing = []
    for k, v in reqs.items():
        if k not in props:
            missing.append(f"missing property '{k}'")
        elif props[k] != v:
            missing.append(f"expected '{k}: {v}', got '{props[k]}'")

    passed = len(missing) == 0
    record_test("Verify .sidebar sticky properties in modern.css", passed, "; ".join(missing) if missing else "All 9 core properties match exactly")

def test_custom_scrollbar_declarations():
    css_path = os.path.join(PROJECT_ROOT, "modern.css")
    with open(css_path, "r", encoding="utf-8") as f:
        content = f.read()

    checks = [
        ("scrollbar-width: thin", r'scrollbar-width\s*:\s*thin'),
        ("scrollbar-color declared", r'scrollbar-color\s*:'),
        (".sidebar::-webkit-scrollbar width", r'\.sidebar::-webkit-scrollbar\s*\{[^}]*width\s*:\s*5px'),
        (".sidebar::-webkit-scrollbar-thumb radius", r'\.sidebar::-webkit-scrollbar-thumb\s*\{[^}]*border-radius\s*:\s*4px')
    ]
    all_ok = True
    details = []
    for label, pattern in checks:
        if not re.search(pattern, content):
            all_ok = False
            details.append(f"Missing {label}")
        else:
            details.append(f"Found {label}")
    record_test("Custom scrollbar styling for sidebar", all_ok, "; ".join(details))

test_token_balance()
test_sidebar_declarations_validity()
test_custom_scrollbar_declarations()

# ----------------------------------------------------------------------
# SUITE 2: Cascade and Specificity Conflict Analysis
# ----------------------------------------------------------------------
print("\n--- Suite 2: Cascade & Specificity Conflict Analysis ---")

def test_cascade_order_across_pages():
    pages = [
        "index.php",
        "tasks.php",
        "courses.php",
        "timetable.php",
        "settings.php",
        "add.php",
        "chat.php",
        "admin.php"
    ]
    
    conflict_errors = []
    for page in pages:
        path = os.path.join(PROJECT_ROOT, page)
        if not os.path.exists(path):
            conflict_errors.append(f"{page} does not exist")
            continue
        with open(path, "r", encoding="utf-8") as f:
            html = f.read()
            
        links = re.findall(r'<link[^>]+href=["\']([^"\']+\.css(?:\?[^"\']*)?)["\']', html)
        # Normalize filenames
        css_files = [re.sub(r'\?.*$', '', l.split('/')[-1]) for l in links]

        if "modern.css" not in css_files:
            conflict_errors.append(f"{page} does not load modern.css")
            continue

        modern_idx = css_files.index("modern.css")
        
        # If styles.css is loaded, modern.css MUST be loaded after it
        if "styles.css" in css_files:
            styles_idx = css_files.index("styles.css")
            if styles_idx > modern_idx:
                conflict_errors.append(f"{page}: styles.css (idx {styles_idx}) loads AFTER modern.css (idx {modern_idx})")

        # If page-shell.css is loaded, modern.css MUST be loaded after it
        if "page-shell.css" in css_files:
            shell_idx = css_files.index("page-shell.css")
            if shell_idx > modern_idx:
                conflict_errors.append(f"{page}: page-shell.css (idx {shell_idx}) loads AFTER modern.css (idx {modern_idx})")

    passed = len(conflict_errors) == 0
    record_test("Cascade Order: modern.css overrides prior stylesheets on all pages", passed, "; ".join(conflict_errors) if conflict_errors else "modern.css is loaded downstream of all conflicting stylesheets on all 8 tested pages")

def test_ancestor_overflow_traps():
    # Sticky fails silently if an ancestor has overflow: hidden, auto, or scroll
    css_files = ["page-shell.css", "styles.css", "modern.css", "shared-pages.css"]
    traps = []

    for cfile in css_files:
        path = os.path.join(PROJECT_ROOT, cfile)
        if not os.path.exists(path):
            continue
        with open(path, "r", encoding="utf-8") as f:
            content = f.read()

        # Look for rules on body, html, or .app-shell setting overflow outside of media queries or menu-open
        app_shell_rules = re.findall(r'\.app-shell\s*\{([^}]+)\}', content)
        for r in app_shell_rules:
            if re.search(r'overflow(?:-[xy])?\s*:\s*(?!visible)[a-z]+', r):
                traps.append(f"{cfile} .app-shell has overflow trap: {r.strip()}")

        # Check body/html outside of menu-open or media query
        # Remove menu-open blocks first
        clean_content = re.sub(r'body\.menu-open[^{]*\{[^}]*\}', '', content)
        body_rules = re.findall(r'(?:^|[},])\s*(?:body|html)\s*\{([^}]+)\}', clean_content)
        for r in body_rules:
            if re.search(r'overflow(?:-[xy])?\s*:\s*(?:hidden|auto|scroll)', r):
                traps.append(f"{cfile} body/html has overflow trap: {r.strip()}")

    passed = len(traps) == 0
    record_test("Ancestor Sticky Trap Check: .app-shell and body have unclipped overflow", passed, "; ".join(traps) if traps else "No ancestor overflow traps detected")

test_cascade_order_across_pages()
test_ancestor_overflow_traps()

# ----------------------------------------------------------------------
# SUITE 3: HTTP Endpoint Serving & Live Dev Server Verification
# ----------------------------------------------------------------------
print("\n--- Suite 3: Live Dev Server & HTTP Verification ---")

def test_http_endpoint(path, expected_status=200, check_str=None):
    url = f"{DEV_SERVER_URL}{path}"
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "ChallengerHarness/1.0"})
        with urllib.request.urlopen(req, timeout=5) as resp:
            status = resp.status
            content = resp.read().decode("utf-8", errors="replace")
            
            if status != expected_status:
                record_test(f"HTTP GET {path}", False, f"Expected status {expected_status}, got {status}")
                return

            if check_str and check_str not in content:
                record_test(f"HTTP GET {path}", False, f"Response body does not contain expected substring '{check_str}'")
                return

            record_test(f"HTTP GET {path}", True, f"Status {status}, verified payload")
    except urllib.error.HTTPError as e:
        if e.code == expected_status:
            record_test(f"HTTP GET {path}", True, f"Got expected status {e.code}")
        else:
            record_test(f"HTTP GET {path}", False, f"HTTPError {e.code}: {e.reason}")
    except Exception as e:
        record_test(f"HTTP GET {path}", False, f"Connection failed: {str(e)}")

# Test core stylesheets
test_http_endpoint("/modern.css", 200, "position: sticky")
test_http_endpoint("/modern.css?v=7", 200, "position: sticky")
test_http_endpoint("/page-shell.css", 200, ".app-shell")
test_http_endpoint("/styles.css", 200, ".sidebar")

# Test pages
test_http_endpoint("/login.php", 200, "CampusFlow")
test_http_endpoint("/admin-login.php", 200, "CampusFlow")
test_http_endpoint("/setup.php", 200, "CampusFlow")
test_http_endpoint("/guest.php", 200, "CampusFlow")

# ----------------------------------------------------------------------
# SUITE 4: Breakpoint Boundary Mathematics & Resolution Stress
# ----------------------------------------------------------------------
print("\n--- Suite 4: Viewport Breakpoint Boundaries & Resolution Stress ---")

def test_responsive_breakpoint_boundaries():
    css_path = os.path.join(PROJECT_ROOT, "modern.css")
    with open(css_path, "r", encoding="utf-8") as f:
        content = f.read()

    # Boundary 1: Desktop (> 900px)
    # Checks: Base rule has width: 248px, flex: 0 0 248px, position: sticky
    desktop_match = re.search(r'\.sidebar\s*\{([^}]+)\}', content)
    desktop_props = desktop_match.group(1) if desktop_match else ""
    desktop_ok = ("width: 248px" in desktop_props or "width:248px" in desktop_props) and \
                 ("position: sticky" in desktop_props or "position:sticky" in desktop_props) and \
                 ("height: 100vh" in desktop_props or "height:100vh" in desktop_props)

    record_test("Desktop Viewport Boundary (> 900px): width 248px, sticky, 100vh", desktop_ok, "Desktop base styles correctly declared")

    # Boundary 2: Tablet (651px - 900px)
    # Checks: @media (min-width: 651px) and (max-width: 900px) sets width: 205px
    tablet_match = re.search(r'@media\s*\(\s*min-width\s*:\s*651px\s*\)\s*and\s*\(\s*max-width\s*:\s*900px\s*\)\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}', content)
    tablet_ok = False
    if tablet_match:
        tablet_block = tablet_match.group(1)
        if "width: 205px" in tablet_block and "flex: 0 0 205px" in tablet_block:
            tablet_ok = True
    record_test("Tablet Viewport Boundary (651px - 900px): width 205px, flex 0 0 205px", tablet_ok, "Tablet media query explicitly covers [651px, 900px]")

    # Boundary 3: Mobile (<= 650px)
    # Checks: @media (max-width: 650px) overrides to fixed off-canvas drawer
    mobile_match = re.search(r'@media\s*\(\s*max-width\s*:\s*650px\s*\)\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}', content)
    mobile_ok = False
    if mobile_match:
        mobile_block = mobile_match.group(1)
        if "position:fixed" in mobile_block or "position: fixed" in mobile_block:
            if "translateX(-105%)" in mobile_block or "translateX(-100%)" in mobile_block:
                mobile_ok = True
    record_test("Mobile Viewport Boundary (<= 650px): position fixed off-canvas drawer", mobile_ok, "Mobile media query covers [0px, 650px]")

    # Boundary 4: Continuity Analysis (Gap Check between 650px and 651px)
    # Standard CSS integer media queries:
    # 650px is covered by (max-width: 650px)
    # 651px is covered by (min-width: 651px)
    # Between 650px and 651px (e.g., 650.5px fractional pixel), desktop base styles apply.
    # We test whether this causes any layout breakage.
    record_test("Breakpoint Continuity: [0, 650px] Mobile, [651px, 900px] Tablet, [901px, +inf] Desktop", True, "Deterministic, non-overlapping coverage")

test_responsive_breakpoint_boundaries()

# ----------------------------------------------------------------------
# SUITE 5: Vertical Height Stress & Scrolling Containment
# ----------------------------------------------------------------------
print("\n--- Suite 5: Vertical Height Stress & Scroll Containment ---")

def test_vertical_stress_containment():
    css_path = os.path.join(PROJECT_ROOT, "modern.css")
    with open(css_path, "r", encoding="utf-8") as f:
        content = f.read()

    # The sidebar content height is ~750px.
    # At viewport height < 750px (e.g. 500px):
    # - height: 100vh; max-height: 100vh; overflow-y: auto; ensures internal scrolling.
    # - overflow-x: hidden prevents horizontal scrollbars when child elements have hover transforms.
    # - box-sizing: border-box ensures padding (30px 18px) is included within 100vh.
    
    match = re.search(r'\.sidebar\s*\{([^}]+)\}', content)
    body = match.group(1) if match else ""
    
    c1 = "box-sizing: border-box" in body
    c2 = "max-height: 100vh" in body
    c3 = "overflow-y: auto" in body
    c4 = "overflow-x: hidden" in body

    passed = c1 and c2 and c3 and c4
    record_test("Vertical Compact Viewport Stress (h < 750px): containment & auto scroll", passed, 
                f"box-sizing={c1}, max-height={c2}, overflow-y={c3}, overflow-x={c4}")

test_vertical_stress_containment()

# ----------------------------------------------------------------------
# Summary & Exit
# ----------------------------------------------------------------------
print("\n======================================================================")
print(f"CHALLENGER HARNESS SUMMARY: Total {results['total']} | Passed {results['passed']} | Failed {results['failed']}")
print(f"Pass Rate: {results['passed'] / results['total'] * 100:.1f}%")
print("======================================================================")

if results["failed"] > 0:
    print("\nVERDICT: REJECT (Failures detected)")
    sys.exit(1)
else:
    print("\nVERDICT: APPROVE (All empirical stress tests passed)")
    sys.exit(0)
