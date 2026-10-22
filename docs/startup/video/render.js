// Davomat videosini yig'adi: sahna.html ni kadrma-kadr chizib, MP4 ga yozadi
// va yoniga .srt subtitr faylini qo'yadi.
//
//   FFMPEG=/yo'l/ffmpeg node render.js uz davomat-uz.mp4
//   FFMPEG=/yo'l/ffmpeg node render.js ru davomat-ru.mp4
//
// Kerak: Node, `playwright` paketi (Chromium bilan) va libx264 li ffmpeg.
// Subtitr kadrga yozilgan (Telegram va telefonlarda doim ko'rinadi); .srt
// YouTube kabi joylarga alohida yuklash uchun.

const { chromium } = require('playwright');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const FPS = 25;
const [lang, out] = process.argv.slice(2);
if (!['uz', 'ru'].includes(lang) || !out) {
  console.error('ishlatish: node render.js uz|ru chiqish.mp4');
  process.exit(1);
}
const ffmpegBin = process.env.FFMPEG || 'ffmpeg';

function srtTime(s) {
  const ms = Math.round(s * 1000);
  const p = (n, w) => String(n).padStart(w, '0');
  return `${p(Math.floor(ms / 3600000), 2)}:${p(Math.floor(ms / 60000) % 60, 2)}:${p(Math.floor(ms / 1000) % 60, 2)},${p(ms % 1000, 3)}`;
}

(async () => {
  const browser = await chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {});
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto('file://' + path.join(__dirname, 'sahna.html') + '?lang=' + lang);
  await page.evaluate(() => document.fonts.ready);

  const duration = await page.evaluate(() => window.DURATION);
  const cues = await page.evaluate((l) => window.CUES[l], lang);
  fs.writeFileSync(
    out.replace(/\.mp4$/, '') + '.srt',
    cues.map((c, i) => `${i + 1}\n${srtTime(c[0])} --> ${srtTime(c[1])}\n${c[2]}\n`).join('\n'),
  );

  const ff = spawn(ffmpegBin, [
    '-y', '-loglevel', 'error',
    '-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'mjpeg', '-i', '-',
    '-c:v', 'libx264', '-preset', 'medium', '-crf', '20', '-pix_fmt', 'yuv420p',
    '-movflags', '+faststart',
    '-metadata', `title=Davomat (${lang})`,
    '-metadata:s:v:0', `language=${lang === 'uz' ? 'uzb' : 'rus'}`,
    out,
  ], { stdio: ['pipe', 'inherit', 'inherit'] });

  const frames = Math.round(duration * FPS);
  for (let f = 0; f < frames; f++) {
    await page.evaluate((t) => window.render(t), f / FPS);
    const jpg = await page.screenshot({ type: 'jpeg', quality: 92 });
    if (!ff.stdin.write(jpg)) await new Promise((r) => ff.stdin.once('drain', r));
    if (f % (FPS * 10) === 0) process.stdout.write(`${lang}: ${Math.round(f / FPS)}s / ${duration}s\n`);
  }
  ff.stdin.end();
  const code = await new Promise((r) => ff.on('close', r));
  await browser.close();
  if (errors.length) { console.error(errors.join('\n')); process.exit(1); }
  if (code !== 0) process.exit(code);
  console.log(`tayyor: ${out}`);
})();
