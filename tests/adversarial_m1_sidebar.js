/**
 * Adversarial Empirical Stress Test Suite for Milestone M1 (Feature F1: Sticky Sidebar)
 * Uses native Chromium/Edge Headless with Chrome DevTools Protocol (CDP)
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BROWSER_PATH = fs.existsSync(EDGE_PATH) ? EDGE_PATH : CHROME_PATH;

const PORT = 9223;

// Helper to sleep
const sleep = ms => new Promise(r => setTimeout(r, ms));

// Simple CDP client over native WebSocket
class CdpClient {
  constructor(wsUrl) {
    this.wsUrl = wsUrl;
    this.ws = null;
    this.id = 1;
    this.callbacks = new Map();
  }

  async connect() {
    return new Promise((resolve, reject) => {
      this.ws = new WebSocket(this.wsUrl);
      this.ws.onopen = () => resolve();
      this.ws.onerror = err => reject(err);
      this.ws.onmessage = msg => {
        const data = JSON.parse(msg.data);
        if (data.id && this.callbacks.has(data.id)) {
          const cb = this.callbacks.get(data.id);
          this.callbacks.delete(data.id);
          if (data.error) cb.reject(new Error(JSON.stringify(data.error)));
          else cb.resolve(data.result);
        }
      };
    });
  }

  async send(method, params = {}) {
    const id = this.id++;
    return new Promise((resolve, reject) => {
      this.callbacks.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }

  async eval(expression) {
    const res = await this.send('Runtime.evaluate', {
      expression,
      returnByValue: true,
      awaitPromise: true
    });
    if (res.exceptionDetails) {
      throw new Error(res.exceptionDetails.text || 'Eval exception');
    }
    return res.result ? res.result.value : undefined;
  }

  async setViewport(width, height) {
    await this.send('Emulation.setDeviceMetricsOverride', {
      width,
      height,
      deviceScaleFactor: 1,
      mobile: width <= 650
    });
  }

  close() {
    if (this.ws) this.ws.close();
  }
}

// Generate the HTML fixture embedding the exact project CSS and HTML
function buildHtmlFixture(options = {}) {
  const projectRoot = path.resolve(__dirname, '..');
  const pageShellCss = fs.readFileSync(path.join(projectRoot, 'page-shell.css'), 'utf-8');
  const sharedPagesCss = fs.readFileSync(path.join(projectRoot, 'shared-pages.css'), 'utf-8');
  const modernCss = fs.readFileSync(path.join(projectRoot, 'modern.css'), 'utf-8');

  const mainContentHeight = options.mainContentHeight || 'auto';
  const mainContentWidth = options.mainContentWidth || 'auto';
  const studentName = options.studentName || 'Alexandre Martin';
  const extraNavItems = options.extraNavItems || '';
  const isAdmin = options.isAdmin || false;

  return `<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Adversarial Test Page</title>
  <style>
    ${pageShellCss}
    ${sharedPagesCss}
    ${modernCss}
  </style>
</head>
<body ${isAdmin ? 'data-page="admin"' : ''}>
  <div class="app-shell ${isAdmin ? 'admin-shell' : ''}">
    ${!isAdmin ? `
    <button class="mobile-menu-toggle" type="button" aria-controls="student-sidebar" aria-expanded="false">
      <span></span><span></span><span></span><span class="sr-only">Ouvrir le menu</span>
    </button>
    <div class="sidebar-backdrop" data-sidebar-close></div>
    <aside class="sidebar" id="student-sidebar">
      <a class="brand" href="index.php" aria-label="CampusFlow accueil">
        <span class="brand-mark">C</span><span>Campus<span>Flow</span></span>
      </a>
      <p class="sidebar-label">Espace étudiant</p>
      <nav aria-label="Navigation principale">
        <a class="nav-item active" href="index.php"><span class="icon">▣</span>Vue d'ensemble</a>
        <a class="nav-item" href="tasks.php"><span class="icon">✓</span>Mes tâches</a>
        <a class="nav-item" href="add.php"><span class="icon">+</span>Ajouter une tâche</a>
        <a class="nav-item" href="courses.php"><span class="icon">▣</span>Ressources</a>
        <a class="nav-item" href="chat.php"><span class="icon">💬</span>Chat promo</a>
        <a class="nav-item" href="timetable.php"><span class="icon">▦</span>Emploi du temps</a>
        <a class="nav-item" href="settings.php"><span class="icon">⚙</span>Paramètres</a>
        ${extraNavItems}
      </nav>
      <a class="account-card" href="settings.php">
        <span class="avatar">A</span>
        <span class="account-copy"><strong>${studentName}</strong><small>Compte étudiant</small></span>
        <span class="account-arrow" aria-hidden="true">→</span>
      </a>
      <button class="sidebar-logout" type="button" data-logout>Se déconnecter</button>
      <div class="sidebar-tip">
        <strong>Petit conseil</strong>
        <p>Commence par la tâche la plus proche. Une petite victoire débloque souvent le reste.</p>
      </div>
      <div class="sidebar-footer">Espace privé de la promotion.</div>
    </aside>
    ` : `
    <aside class="sidebar">
      <a class="brand" href="admin.php"><span class="brand-mark">C</span><span>Campus<span>Flow</span></span></a>
      <p class="sidebar-label">Administration</p>
      <nav><a class="nav-item active" href="#proposals"><span class="icon">▦</span> Modération</a></nav>
      <button class="sidebar-logout" id="admin-logout" type="button">Se déconnecter</button>
      <div class="sidebar-footer">Panneau indépendant de l’espace étudiant.</div>
    </aside>
    `}
    <main class="page-content" style="min-height: ${mainContentHeight};">
      <div style="width: ${mainContentWidth};">
        <h1>Page Content Heading</h1>
        <p>Page content body paragraph.</p>
        <div id="content-spacer" style="height: ${mainContentHeight === 'auto' ? '200px' : mainContentHeight};"></div>
      </div>
    </main>
  </div>
</body>
</html>`;
}

async function runAdversarialSuite() {
  console.log('==============================================================================');
  console.log('   CAMPUSFLOW ADVERSARIAL STRESS TEST SUITE: M1 STICKY SIDEBAR (CDP)         ');
  console.log('==============================================================================\n');

  // 1. Launch Browser
  const browserProc = spawn(BROWSER_PATH, [
    '--headless=new',
    `--remote-debugging-port=${PORT}`,
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-gpu',
    'about:blank'
  ]);

  let cdp = null;
  const results = [];

  function record(suite, name, passed, detail) {
    results.push({ suite, name, passed, detail });
    const tag = passed ? '[PASS]' : '[FAIL]';
    console.log(`  ${tag} [${suite}] ${name}`);
    if (!passed) console.log(`         Reason: ${detail}`);
  }

  try {
    // Wait for CDP endpoint
    let connected = false;
    for (let i = 0; i < 20; i++) {
      await sleep(200);
      try {
        const res = await fetch(`http://127.0.0.1:${PORT}/json/list`);
        const pages = await res.json();
        if (pages.length > 0 && pages[0].webSocketDebuggerUrl) {
          cdp = new CdpClient(pages[0].webSocketDebuggerUrl);
          await cdp.connect();
          await cdp.send('Page.enable');
          await cdp.send('Runtime.enable');
          connected = true;
          break;
        }
      } catch (e) {}
    }

    if (!connected) throw new Error('Could not connect to browser CDP');
    console.log('Connected to Headless Browser Engine via CDP.\n');

    // Helper to load HTML
    async function loadHtml(html) {
      const dataUri = 'data:text/html;charset=utf-8,' + encodeURIComponent(html);
      await cdp.send('Page.navigate', { url: dataUri });
      await sleep(300);
    }

    // ------------------------------------------------------------------------
    // SUITE 1: Flex Layout, Bounding Boxes & Horizontal Containment in .app-shell
    // ------------------------------------------------------------------------
    console.log('--- Suite 1: Flex Container & Horizontal Overflow Side Effects ---');
    {
      const html = buildHtmlFixture();
      await loadHtml(html);
      await cdp.setViewport(1440, 900);

      // Test 1.1: .app-shell computed display flex
      const appShellDisplay = await cdp.eval('window.getComputedStyle(document.querySelector(".app-shell")).display');
      record('SUITE-1', 'AppShellComputedDisplayIsFlex', appShellDisplay === 'flex', `Expected 'flex', got '${appShellDisplay}'`);

      // Test 1.2: .sidebar computed styles
      const sidebarStyles = await cdp.eval(`(() => {
        const cs = window.getComputedStyle(document.querySelector(".sidebar"));
        return {
          position: cs.position,
          top: cs.top,
          height: cs.height,
          width: cs.width,
          flexShrink: cs.flexShrink,
          flexGrow: cs.flexGrow,
          overflowY: cs.overflowY,
          overflowX: cs.overflowX,
          boxSizing: cs.boxSizing
        };
      })()`);

      record('SUITE-1', 'SidebarStickyPositioning', sidebarStyles.position === 'sticky', `Expected sticky, got ${sidebarStyles.position}`);
      record('SUITE-1', 'SidebarTopZero', sidebarStyles.top === '0px', `Expected 0px, got ${sidebarStyles.top}`);
      record('SUITE-1', 'SidebarHeight100vh', sidebarStyles.height === '900px', `Expected 900px, got ${sidebarStyles.height}`);
      record('SUITE-1', 'SidebarDesktopWidth248px', sidebarStyles.width === '248px', `Expected 248px, got ${sidebarStyles.width}`);
      record('SUITE-1', 'SidebarFlexShrinkZero', sidebarStyles.flexShrink === '0', `Expected flex-shrink: 0, got ${sidebarStyles.flexShrink}`);
      record('SUITE-1', 'SidebarOverflowYAuto', sidebarStyles.overflowY === 'auto', `Expected overflow-y: auto, got ${sidebarStyles.overflowY}`);
      record('SUITE-1', 'SidebarOverflowXHidden', sidebarStyles.overflowX === 'hidden', `Expected overflow-x: hidden, got ${sidebarStyles.overflowX}`);
      record('SUITE-1', 'SidebarBoxSizingBorderBox', sidebarStyles.boxSizing === 'border-box', `Expected border-box, got ${sidebarStyles.boxSizing}`);

      // Test 1.3: Ultra-wide content stress test (3000px inside main content)
      const wideHtml = buildHtmlFixture({ mainContentWidth: '3000px' });
      await loadHtml(wideHtml);
      await cdp.setViewport(1440, 900);

      const wideMetrics = await cdp.eval(`(() => {
        const sidebar = document.querySelector(".sidebar");
        const rect = sidebar.getBoundingClientRect();
        return {
          rectWidth: Math.round(rect.width),
          scrollWidth: sidebar.scrollWidth,
          clientWidth: sidebar.clientWidth,
          hasInternalHScroll: sidebar.scrollWidth > sidebar.clientWidth,
          rectLeft: rect.left
        };
      })()`);

      record('SUITE-1', 'SidebarWidthPreservedUnder3000pxContent', wideMetrics.rectWidth === 248, `Sidebar width collapsed to ${wideMetrics.rectWidth}px`);
      record('SUITE-1', 'SidebarLeftPinnedUnderWideContent', wideMetrics.rectLeft === 0, `Sidebar left displaced to ${wideMetrics.rectLeft}px`);
      record('SUITE-1', 'NoInternalHorizontalScrollbarInSidebar', !wideMetrics.hasInternalHScroll, `Internal scrollWidth ${wideMetrics.scrollWidth} > clientWidth ${wideMetrics.clientWidth}`);
    }

    // ------------------------------------------------------------------------
    // SUITE 2: Extreme Tall Content (5000px) vs Short Content (< Viewport)
    // ------------------------------------------------------------------------
    console.log('\n--- Suite 2: Extreme Tall Content (5000px) vs Short Content (< Viewport) ---');
    {
      // Test 2.1: 5000px content vertical scroll behavior
      const tallHtml = buildHtmlFixture({ mainContentHeight: '5000px' });
      await loadHtml(tallHtml);
      await cdp.setViewport(1440, 900);

      const docHeight = await cdp.eval('document.documentElement.scrollHeight');
      record('SUITE-2', 'PageDocHeightExceeds5000px', docHeight >= 5000, `Expected >= 5000px, got ${docHeight}px`);

      // Scroll positions to test
      const scrollOffsets = [0, 500, 1500, 3000, 4000];
      let stickyMaintainedAllOffsets = true;
      let failureDetail = '';

      for (const y of scrollOffsets) {
        await cdp.eval(`window.scrollTo(0, ${y})`);
        await sleep(50);
        const rect = await cdp.eval('document.querySelector(".sidebar").getBoundingClientRect()');
        // rect.top should remain pinned at 0 within viewport
        if (Math.abs(rect.top) > 1) {
          stickyMaintainedAllOffsets = false;
          failureDetail = `At scrollY=${y}, sidebar rect.top was ${rect.top} instead of 0`;
          break;
        }
        if (Math.abs(rect.left) > 1) {
          stickyMaintainedAllOffsets = false;
          failureDetail = `At scrollY=${y}, sidebar rect.left was ${rect.left} instead of 0`;
          break;
        }
        if (Math.round(rect.height) !== 900) {
          stickyMaintainedAllOffsets = false;
          failureDetail = `At scrollY=${y}, sidebar height was ${rect.height} instead of 900`;
          break;
        }
      }
      record('SUITE-2', 'SidebarStaysStickyThroughout5000pxScroll', stickyMaintainedAllOffsets, failureDetail || 'Sidebar pinned at top:0 across all offsets [0, 500, 1500, 3000, 4000]');

      // Test 2.2: Short content (< viewport)
      const shortHtml = buildHtmlFixture({ mainContentHeight: '150px' });
      await loadHtml(shortHtml);
      await cdp.setViewport(1440, 900);

      const shortMetrics = await cdp.eval(`(() => {
        const sidebar = document.querySelector(".sidebar");
        const rect = sidebar.getBoundingClientRect();
        return {
          sidebarHeight: Math.round(rect.height),
          bodyScrollHeight: document.body.scrollHeight,
          docScrollHeight: document.documentElement.scrollHeight,
          windowHeight: window.innerHeight
        };
      })()`);

      record('SUITE-2', 'ShortContentSidebarFillsFullViewport', shortMetrics.sidebarHeight === 900, `Expected 900px, got ${shortMetrics.sidebarHeight}px`);
      record('SUITE-2', 'ShortContentDoesNotCauseExtraneousPageScroll', shortMetrics.docScrollHeight <= 900, `Document scrollHeight ${shortMetrics.docScrollHeight} > windowHeight ${shortMetrics.windowHeight}`);

      // Test 2.3: Constrained Viewport Height (500px height with 760px sidebar content)
      await cdp.setViewport(1200, 500);
      const compactMetrics = await cdp.eval(`(() => {
        const sidebar = document.querySelector(".sidebar");
        const initialClientHeight = sidebar.clientHeight;
        const initialScrollHeight = sidebar.scrollHeight;

        // Try internal scroll
        sidebar.scrollTop = 250;
        const scrolledTop = sidebar.scrollTop;

        // Check if footer and logout are accessible
        const logout = sidebar.querySelector(".sidebar-logout");
        const tip = sidebar.querySelector(".sidebar-tip");
        const footer = sidebar.querySelector(".sidebar-footer");

        return {
          clientHeight: initialClientHeight,
          scrollHeight: initialScrollHeight,
          isOverflowingVertically: initialScrollHeight > initialClientHeight,
          scrolledTop,
          logoutExists: !!logout,
          tipExists: !!tip,
          footerExists: !!footer
        };
      })()`);

      record('SUITE-2', 'CompactViewportSidebarTriggersInternalScroll', compactMetrics.isOverflowingVertically, `scrollHeight (${compactMetrics.scrollHeight}) should exceed clientHeight (${compactMetrics.clientHeight})`);
      record('SUITE-2', 'CompactViewportSidebarScrollTopOperable', compactMetrics.scrolledTop > 200, `sidebar.scrollTop expected > 200, got ${compactMetrics.scrolledTop}`);
    }

    // ------------------------------------------------------------------------
    // SUITE 3: Element-Level Stress & Adversarial Edge Cases
    // ------------------------------------------------------------------------
    console.log('\n--- Suite 3: Sidebar Elements Overflow & Clipping Verification ---');
    {
      // Test 3.1: Ultra-long student name (boundary text blowout)
      const longName = 'Prof. Maximilien-Alexandre de la Tour-d’Auvergne de Montmorency-Laval';
      const longNameHtml = buildHtmlFixture({ studentName: longName });
      await loadHtml(longNameHtml);
      await cdp.setViewport(1440, 900);

      const nameMetrics = await cdp.eval(`(() => {
        const sidebar = document.querySelector(".sidebar");
        const accountCard = sidebar.querySelector(".account-card");
        const strong = accountCard.querySelector("strong");
        const sRect = sidebar.getBoundingClientRect();
        const aRect = accountCard.getBoundingClientRect();
        const cs = window.getComputedStyle(strong);

        return {
          cardFitsInsideSidebar: aRect.right <= (sRect.right + 1),
          textOverflowEllipsis: cs.textOverflow === 'ellipsis',
          whiteSpaceNowrap: cs.whiteSpace === 'nowrap',
          overflowHidden: cs.overflow === 'hidden'
        };
      })()`);

      record('SUITE-3', 'LongStudentNameConfinedInsideSidebar', nameMetrics.cardFitsInsideSidebar, 'Account card blew out beyond sidebar width');
      record('SUITE-3', 'LongStudentNameEllipsizedGracefully', nameMetrics.textOverflowEllipsis && nameMetrics.whiteSpaceNowrap && nameMetrics.overflowHidden, 'Missing ellipsis/overflow-hidden rules on strong');

      // Test 3.2: Hover micro-interaction transform: translateX(2px)
      const hoverMetrics = await cdp.eval(`(() => {
        const sidebar = document.querySelector(".sidebar");
        const navItem = sidebar.querySelector(".nav-item");

        // Apply hover style manually to simulate :hover
        navItem.style.transform = 'translateX(2px)';
        const hasHScroll = sidebar.scrollWidth > sidebar.clientWidth;
        navItem.style.transform = '';

        return {
          hasHScroll,
          scrollWidth: sidebar.scrollWidth,
          clientWidth: sidebar.clientWidth
        };
      })()`);

      record('SUITE-3', 'NavItemHoverTranslationDoesNotTriggerHScroll', !hoverMetrics.hasHScroll, `Hover triggered horizontal scroll: scrollWidth ${hoverMetrics.scrollWidth} > clientWidth ${hoverMetrics.clientWidth}`);

      // Test 3.3: Admin Shell Sticky Sidebar Check
      const adminHtml = buildHtmlFixture({ isAdmin: true, mainContentHeight: '3000px' });
      await loadHtml(adminHtml);
      await cdp.setViewport(1440, 900);

      await cdp.eval('window.scrollTo(0, 1000)');
      await sleep(50);
      const adminRect = await cdp.eval('document.querySelector(".sidebar").getBoundingClientRect()');
      record('SUITE-3', 'AdminShellSidebarStickyBehavior', Math.abs(adminRect.top) <= 1, `Admin sidebar top at scrollY=1000 was ${adminRect.top}`);
    }

    // ------------------------------------------------------------------------
    // SUITE 4: Responsive Breakpoints & Mobile Drawer
    // ------------------------------------------------------------------------
    console.log('\n--- Suite 4: Responsive Breakpoints & Mobile Drawer ---');
    {
      const responsiveHtml = buildHtmlFixture();
      await loadHtml(responsiveHtml);

      // Test 4.1: Tablet width (768px)
      await cdp.setViewport(768, 900);
      const tabletMetrics = await cdp.eval(`(() => {
        const sidebar = document.querySelector(".sidebar");
        const cs = window.getComputedStyle(sidebar);
        const rect = sidebar.getBoundingClientRect();
        return {
          width: cs.width,
          rectWidth: Math.round(rect.width),
          position: cs.position
        };
      })()`);

      record('SUITE-4', 'TabletWidthAdaptsTo205px', tabletMetrics.rectWidth === 205, `Expected 205px on tablet, got ${tabletMetrics.rectWidth}px`);
      record('SUITE-4', 'TabletStickyPreserved', tabletMetrics.position === 'sticky', `Expected sticky on tablet, got ${tabletMetrics.position}`);

      // Test 4.2: Mobile Viewport (400px)
      await cdp.setViewport(400, 800);
      const mobileClosed = await cdp.eval(`(() => {
        const sidebar = document.querySelector(".sidebar");
        const toggle = document.querySelector(".mobile-menu-toggle");
        const backdrop = document.querySelector(".sidebar-backdrop");
        const csSidebar = window.getComputedStyle(sidebar);
        const csToggle = window.getComputedStyle(toggle);
        const csBackdrop = window.getComputedStyle(backdrop);
        const rect = sidebar.getBoundingClientRect();

        return {
          sidebarPosition: csSidebar.position,
          toggleDisplayed: csToggle.display !== 'none',
          backdropHidden: csBackdrop.display === 'none',
          isOffScreen: rect.right <= 0
        };
      })()`);

      record('SUITE-4', 'MobileSidebarPositionFixed', mobileClosed.sidebarPosition === 'fixed', `Expected fixed, got ${mobileClosed.sidebarPosition}`);
      record('SUITE-4', 'MobileSidebarHiddenOffScreenInitially', mobileClosed.isOffScreen, 'Mobile sidebar visible before menu open');
      record('SUITE-4', 'MobileMenuToggleVisible', mobileClosed.toggleDisplayed, 'Mobile menu toggle not visible on mobile');

      // Test 4.3: Mobile Menu Open Drawer
      const mobileOpened = await cdp.eval(`(() => {
        document.body.classList.add("menu-open");
        const sidebar = document.querySelector(".sidebar");
        const backdrop = document.querySelector(".sidebar-backdrop");
        const csBackdrop = window.getComputedStyle(backdrop);
        const rect = sidebar.getBoundingClientRect();

        return {
          sidebarVisibleOnScreen: rect.left >= 0 && rect.right > 0,
          backdropDisplayed: csBackdrop.display === 'block',
          bodyOverflowHidden: window.getComputedStyle(document.body).overflow === 'hidden'
        };
      })()`);

      record('SUITE-4', 'MobileDrawerOpensOnMenuOpen', mobileOpened.sidebarVisibleOnScreen, 'Mobile drawer did not slide on-screen');
      record('SUITE-4', 'MobileBackdropVisibleOnMenuOpen', mobileOpened.backdropDisplayed, 'Mobile backdrop not display: block');
      record('SUITE-4', 'MobileBodyScrollLockedWhenDrawerOpen', mobileOpened.bodyOverflowHidden, 'body overflow not hidden when drawer open');
    }

  } catch (err) {
    console.error('\nTest Harness Error:', err);
  } finally {
    if (cdp) cdp.close();
    browserProc.kill();
  }

  // Summary
  console.log('\n==============================================================================');
  const total = results.length;
  const passed = results.filter(r => r.passed).length;
  const failed = total - passed;
  console.log(`SUMMARY: ${total} Total Tests | ${passed} Passed | ${failed} Failed`);
  console.log(`Pass Rate: ${(passed / total * 100).toFixed(1)}%`);
  console.log('==============================================================================\n');

  return { total, passed, failed, results };
}

runAdversarialSuite().then(res => {
  process.exit(res.failed > 0 ? 1 : 0);
});
