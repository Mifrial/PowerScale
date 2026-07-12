const puppeteer = require('puppeteer-core');
const { marked } = require('marked');
const fs = require('fs');
const path = require('path');

const CHROME_PATH = '/usr/bin/google-chrome';
const MAKETS_DIR = path.join(__dirname, 'makets');
const SCREENSHOTS_DIR = path.join(__dirname, 'screenshots');
const TZ_PATH = path.join(__dirname, 'firstAiTZ.md');
const OUTPUT_PDF = path.join(__dirname, 'Техническое_задание.pdf');

// Mockup files to screenshot
const MOCKUPS = [
  { file: 'login.html', label: 'Вход в систему (/login)' },
  { file: 'register.html', label: 'Регистрация (/register)' },
  { file: 'forgot-password.html', label: 'Сброс пароля (/forgot-password)' },
  { file: 'games-list.html', label: 'Список игр (/games)' },
  { file: 'game-new.html', label: 'Создание игры (/games/new)' },
  { file: 'game-detail.html', label: 'Страница игры (/games/:id)' },
  { file: 'game-detail-characters.html', label: 'Персонажи игры (/games/:id/characters)' },
  { file: 'game-moderate.html', label: 'Модерация игры (/games/:id/moderate)' },
  { file: 'characters-list.html', label: 'Список персонажей (/characters)' },
  { file: 'character-new.html', label: 'Создание персонажа (/characters/new)' },
  { file: 'character-card.html', label: 'Карточка персонажа (/characters/:id)' },
  { file: 'character-editor.html', label: 'Редактор персонажа (/characters/:id/edit)' },
  { file: 'character-editor-race.html', label: 'Редактор — выбор расы' },
  { file: 'profile-edit.html', label: 'Редактирование профиля (/users/:id/edit)' },
  { file: 'notifications.html', label: 'Уведомления (/notifications)' },
  { file: 'spaces-list.html', label: 'Список пространств (/spaces)' },
  { file: 'space-new.html', label: 'Создание пространства (/spaces/new)' },
  { file: 'space-rules.html', label: 'Правила пространства (/spaces/:id)' },
  { file: 'space-settings.html', label: 'Настройки пространства (/spaces/:id/settings)' },
  { file: 'space-publish.html', label: 'Публикация пространства (/spaces/:id/publish)' },
  { file: 'space-rule-view.html', label: 'Просмотр правила (/spaces/:id/rules/:ruleId)' },
];

async function takeScreenshots(browser) {
  console.log('Taking screenshots...');
  if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
  }

  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });

  for (const mockup of MOCKUPS) {
    const filePath = path.join(MAKETS_DIR, mockup.file);
    if (!fs.existsSync(filePath)) {
      console.log(`  SKIP: ${mockup.file} not found`);
      continue;
    }

    const url = `file://${filePath}`;
    const screenshotPath = path.join(SCREENSHOTS_DIR, mockup.file.replace('.html', '.png'));

    try {
      await page.goto(url, { waitUntil: 'networkidle0', timeout: 15000 });
      // Wait a bit for rendering
      await new Promise(r => setTimeout(r, 300));

      // Get full page dimensions
      const bodyHandle = await page.$('body');
      const fullHeight = await page.evaluate(() => document.documentElement.scrollHeight);
      
      await page.setViewport({ width: 1280, height: Math.min(fullHeight, 3000) });
      await new Promise(r => setTimeout(r, 300));
      
      await page.screenshot({ path: screenshotPath, fullPage: true });
      console.log(`  OK: ${mockup.file} -> ${screenshotPath}`);
    } catch (err) {
      console.log(`  ERROR: ${mockup.file} - ${err.message}`);
      // Create a placeholder image
      const { createCanvas } = (() => {
        try { return require('canvas'); } catch(e) { return null; }
      })();
    }
  }

  await page.close();
}

function tzSectionToMockups(sectionTitle) {
  // Map TZ sections to relevant mockup labels
  const map = {
    'Аутентификация': ['login.html', 'register.html', 'forgot-password.html'],
    'Вход': ['login.html'],
    'Регистрация': ['register.html'],
    'Список игр': ['games-list.html'],
    'Создание игры': ['game-new.html'],
    'Страница игры': ['game-detail.html'],
    'Персонажи игры': ['game-detail-characters.html'],
    'Модерация': ['game-moderate.html', 'game-moderate.html'],
    'Список персонажей': ['characters-list.html'],
    'Создание персонажа': ['character-new.html'],
    'Карточка персонажа': ['character-card.html'],
    'Редактирование персонажа': ['character-editor.html', 'character-editor-race.html'],
    'Редактор персонажа': ['character-editor.html', 'character-editor-race.html'],
    'Редактирование профиля': ['profile-edit.html'],
    'Уведомления': ['notifications.html'],
    'Пространства': ['spaces-list.html', 'space-rules.html'],
    'Создание пространства': ['space-new.html'],
    'Настройки пространства': ['space-settings.html'],
    'Публикация пространства': ['space-publish.html'],
    'Просмотр правила': ['space-rule-view.html'],
    'Заклинания': ['space-rule-view.html'],
    'Предметы': ['character-card.html'],
    'Раздел «Игры»': ['games-list.html', 'game-detail.html'],
    'Раздел «Персонажи»': ['characters-list.html', 'character-card.html'],
    'Раздел «Пользователи»': ['profile-edit.html'],
  };
  for (const [key, files] of Object.entries(map)) {
    if (sectionTitle.includes(key)) {
      return files;
    }
  }
  return [];
}

function renderHtmlScreenshots(files) {
  const seen = new Set();
  const html = [];
  for (const f of files) {
    const pngFile = f.replace('.html', '.png');
    const pngPath = path.join(SCREENSHOTS_DIR, pngFile);
    if (!fs.existsSync(pngPath) || seen.has(f)) continue;
    seen.add(f);
    const mockup = MOCKUPS.find(m => m.file === f);
    const label = mockup ? mockup.label : f;
    html.push(`<figure class="mockup-figure"><figcaption>Макет: ${label}</figcaption><img src="${pngPath}" alt="${label}" /></figure>`);
  }
  return html.join('\n');
}

async function generatePDF() {
  // 1. Take screenshots
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  await takeScreenshots(browser);

  // 2. Read TZ markdown
  const tzMarkdown = fs.readFileSync(TZ_PATH, 'utf-8');

  // 3. Configure marked
  marked.setOptions({
    breaks: true,
    gfm: true,
  });

  // 4. Split markdown into sections for mockup insertion
  const lines = tzMarkdown.split('\n');
  let htmlParts = [];
  let currentSection = '';
  let sectionBuffer = [];

  for (const line of lines) {
    const headerMatch = line.match(/^(#{1,3})\s+(.+)/);
    if (headerMatch) {
      // Flush previous section
      if (sectionBuffer.length > 0) {
        const mdText = sectionBuffer.join('\n');
        const rendered = marked.parse(mdText);
        const mockupFiles = tzSectionToMockups(currentSection);
        const screenshotsHtml = renderHtmlScreenshots(mockupFiles);
        htmlParts.push(rendered);
        if (screenshotsHtml) {
          htmlParts.push(screenshotsHtml);
        }
      }
      currentSection = headerMatch[2];
      sectionBuffer = [line];
    } else {
      sectionBuffer.push(line);
    }
  }
  // Flush last section
  if (sectionBuffer.length > 0) {
    const mdText = sectionBuffer.join('\n');
    const rendered = marked.parse(mdText);
    const mockupFiles = tzSectionToMockups(currentSection);
    const screenshotsHtml = renderHtmlScreenshots(mockupFiles);
    htmlParts.push(rendered);
    if (screenshotsHtml) {
      htmlParts.push(screenshotsHtml);
    }
  }

  const bodyContent = htmlParts.join('\n');

  // 5. Read CSS and build full HTML
  const cssPath = path.join(__dirname, 'makets', 'tz-pdf.css');
  let customCss = '';
  if (fs.existsSync(cssPath)) {
    customCss = fs.readFileSync(cssPath, 'utf-8');
  }

  const fullHtml = `<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<style>
@page {
  size: A4;
  margin: 20mm 25mm;
}

body {
  font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
  font-size: 11pt;
  line-height: 1.5;
  color: #1a1a1a;
  max-width: 100%;
}

/* Typography */
h1 { font-size: 18pt; color: #1a237e; border-bottom: 2px solid #1a237e; padding-bottom: 6pt; margin-top: 28pt; page-break-before: always; }
h1:first-of-type { page-break-before: avoid; }
h2 { font-size: 14pt; color: #283593; border-bottom: 1px solid #c5cae9; padding-bottom: 4pt; margin-top: 22pt; }
h3 { font-size: 12pt; color: #3949ab; margin-top: 16pt; }
h4 { font-size: 11pt; color: #5c6bc0; margin-top: 12pt; }

p { margin: 6pt 0; text-align: justify; }

/* Code blocks */
pre, code {
  font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
  font-size: 9pt;
}
code {
  background: #f5f5f5;
  padding: 1pt 3pt;
  border-radius: 3pt;
  color: #c7254e;
}
pre {
  background: #f8f9fa;
  border: 1px solid #e0e0e0;
  border-left: 3px solid #1a237e;
  padding: 10pt 12pt;
  border-radius: 4pt;
  overflow-x: auto;
  line-height: 1.3;
}
pre code {
  background: none;
  padding: 0;
  color: #333;
}

/* Tables */
table {
  width: 100%;
  border-collapse: collapse;
  margin: 10pt 0;
  font-size: 9.5pt;
}
th, td {
  border: 1px solid #e0e0e0;
  padding: 5pt 8pt;
  text-align: left;
  vertical-align: top;
}
th {
  background: #e8eaf6;
  color: #283593;
  font-weight: 600;
}
tr:nth-child(even) td {
  background: #fafafa;
}

/* Lists */
ul, ol { margin: 6pt 0; padding-left: 22pt; }
li { margin: 3pt 0; }

/* Blockquotes / alerts */
blockquote {
  border-left: 3px solid #ff8f00;
  background: #fff8e1;
  margin: 10pt 0;
  padding: 8pt 12pt;
  border-radius: 0 4pt 4pt 0;
}
blockquote p { margin: 2pt 0; }

/* Horizontal rule */
hr {
  border: none;
  border-top: 1px solid #e0e0e0;
  margin: 16pt 0;
}

/* Mockup figures */
.mockup-figure {
  margin: 14pt 0;
  text-align: center;
  page-break-inside: avoid;
}
.mockup-figure figcaption {
  font-size: 9pt;
  color: #666;
  margin-bottom: 6pt;
  font-style: italic;
}
.mockup-figure img {
  max-width: 100%;
  height: auto;
  border: 1px solid #ccc;
  border-radius: 4pt;
  box-shadow: 0 2pt 6pt rgba(0,0,0,0.1);
}

/* Status badges */
.status-ok { color: #2e7d32; font-weight: bold; }
.status-draft { color: #e65100; font-weight: bold; }

/* Strong emphasis */
strong { color: #1a237e; }

/* Links */
a { color: #1565c0; text-decoration: none; }

${customCss}
</style>
</head>
<body>
${bodyContent}
</body>
</html>`;

  const htmlPath = path.join(__dirname, 'tz-output.html');
  fs.writeFileSync(htmlPath, fullHtml, 'utf-8');
  console.log(`HTML written to ${htmlPath}`);

  // 6. Generate PDF
  console.log('Generating PDF...');
  const pdfPage = await browser.newPage();
  await pdfPage.goto(`file://${htmlPath}`, { waitUntil: 'networkidle0', timeout: 30000 });
  await new Promise(r => setTimeout(r, 1000));

  await pdfPage.pdf({
    path: OUTPUT_PDF,
    format: 'A4',
    margin: { top: '20mm', bottom: '20mm', left: '20mm', right: '20mm' },
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: '<div style="font-size:8pt;color:#999;text-align:center;width:100%">— <span class="pageNumber"></span> —</div>',
  });

  await pdfPage.close();
  await browser.close();

  console.log(`PDF generated: ${OUTPUT_PDF}`);
  const stats = fs.statSync(OUTPUT_PDF);
  console.log(`Size: ${(stats.size / 1024 / 1024).toFixed(2)} MB`);
}

generatePDF().catch(err => {
  console.error('Fatal error:', err);
  process.exit(1);
});
