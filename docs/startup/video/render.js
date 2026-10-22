// Davomat videosini yig'adi: sahna.html ni kadrma-kadr chizib, MP4 ga yozadi
// va yoniga .srt subtitr faylini hamda .jpg muqovani qo'yadi.
//
//   FFMPEG=/yo'l/ffmpeg node render.js uz davomat-uz.mp4
//   FFMPEG=/yo'l/ffmpeg node render.js ru davomat-ru.mp4
//
// Kerak: Node, `playwright` paketi (Chromium bilan) va libx264 li ffmpeg.
// Subtitr kadrga yozilgan (Telegram va telefonlarda doim ko'rinadi); .srt
// YouTube kabi joylarga alohida yuklash uchun.
//
// Ovoz: ovoz/<til>/01.* … 14.* bo'lsa (har subtitr uchun bitta yozuv, format
// istalgan), har bir jumla o'z subtitri boshlanadigan paytga qo'yiladi. Jimlik
// kesiladi, balandlik tenglashtiriladi; jumla o'z vaqtidan uzun bo'lsa 25% gacha
// tezlashtiriladi, undan uzuni xato. Papka bo'sh bo'lsa video ovozsiz chiqadi.
// Boshqa papka: OVOZ_DIR=/yo'l (ichida uz/ va ru/). Matn: diktor-matni.md.

const { chromium } = require('playwright');
const { spawn, spawnSync } = require('child_process');
const os = require('os');
const fs = require('fs');
const path = require('path');

const FPS = 25;
const [lang, out] = process.argv.slice(2);
if (!['uz', 'ru'].includes(lang) || !out) {
  console.error('ishlatish: node render.js uz|ru chiqish.mp4');
  process.exit(1);
}
const ffmpegBin = process.env.FFMPEG || 'ffmpeg';

const MAX_TEMPO = 1.25;

function ffmpegSync(args) {
  const r = spawnSync(ffmpegBin, ['-y', '-loglevel', 'error', ...args], { encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`ffmpeg: ${args.join(' ')}\n${r.stderr}`);
}

// Har bir yozuvni tozalaydi va subtitr vaqtiga joylab bitta ovoz yo'lagiga
// aylantiradi. Ovoz yo'q bo'lsa null qaytaradi.
function buildVoice(cues, duration, tmp) {
  const dir = path.join(process.env.OVOZ_DIR || path.join(__dirname, 'ovoz'), lang);
  const files = fs.existsSync(dir) ? fs.readdirSync(dir).filter((f) => /^\d\d\.[a-z0-9]+$/i.test(f)) : [];
  if (!files.length) {
    console.log(`${lang}: ${dir} da yozuv yo'q, video ovozsiz chiqadi`);
    return null;
  }
  const byNo = {};
  files.forEach((f) => { byNo[+f.slice(0, 2)] = path.join(dir, f); });
  const missing = cues.map((_, i) => i + 1).filter((n) => !byNo[n]);
  if (missing.length) throw new Error(`${lang}: yozuv yetishmaydi: ${missing.map((n) => String(n).padStart(2, '0')).join(', ')}`);

  const clean = 'silenceremove=start_periods=1:start_threshold=-45dB:start_silence=0.08,'
    + 'areverse,silenceremove=start_periods=1:start_threshold=-45dB:start_silence=0.12,areverse,'
    + 'highpass=f=80,loudnorm=I=-18:TP=-2:LRA=7';
  const tooLong = [];
  const parts = cues.map((c, i) => {
    const wav = path.join(tmp, `${lang}-${i + 1}.wav`);
    ffmpegSync(['-i', byNo[i + 1], '-af', clean, '-ar', '48000', '-ac', '1', '-c:a', 'pcm_s16le', wav]);
    const len = (fs.statSync(wav).size - 44) / (48000 * 2);
    const next = i + 1 < cues.length ? cues[i + 1][0] : duration;
    const room = next - c[0] - 0.1;
    const tempo = Math.max(1, len / room);
    if (tempo > MAX_TEMPO) tooLong.push(`${String(i + 1).padStart(2, '0')}: ${len.toFixed(1)}s, joy ${room.toFixed(1)}s`);
    return { wav, start: c[0], tempo };
  });
  if (tooLong.length) throw new Error(`${lang}: bu jumlalar juda uzun, qayta tezroq yozing:\n  ${tooLong.join('\n  ')}`);

  const voice = path.join(tmp, `${lang}-voice.m4a`);
  const inputs = parts.flatMap((p) => ['-i', p.wav]);
  const chains = parts.map((p, i) => {
    const ms = Math.round(p.start * 1000);
    const tempo = p.tempo > 1.001 ? `atempo=${p.tempo.toFixed(3)},` : '';
    return `[${i}:a]${tempo}adelay=${ms}:all=1[a${i}]`;
  });
  const mix = parts.map((_, i) => `[a${i}]`).join('')
    + `amix=inputs=${parts.length}:normalize=0:dropout_transition=0,apad,atrim=0:${duration},`
    + `afade=t=out:st=${duration - 0.6}:d=0.6[out]`;
  ffmpegSync([...inputs, '-filter_complex', [...chains, mix].join(';'), '-map', '[out]',
    '-ar', '48000', '-c:a', 'aac', '-b:a', '128k', voice]);
  const sped = parts.map((p, i) => [i + 1, p.tempo]).filter(([, t]) => t > 1.001);
  if (sped.length) console.log(`${lang}: tezlashtirildi: ${sped.map(([n, t]) => `${n} (×${t.toFixed(2)})`).join(', ')}`);
  return voice;
}

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

  // Ovozni kadrlardan oldin tayyorlaymiz: uzun jumla bo'lsa, 3 daqiqalik yig'ishdan oldin to'xtaymiz.
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'davomat-'));
  let voice;
  try { voice = buildVoice(cues, duration, tmp); } catch (e) { console.error(e.message); await browser.close(); process.exit(1); }
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

  // Muqova (poster): sarlavha kadri, subtitr va chiziqsiz — pleer tugmalari ostida yarim matn qolmasin.
  await page.evaluate(() => {
    window.render(3);
    document.getElementById('sub').style.display = 'none';
    document.getElementById('bar').style.display = 'none';
  });
  await page.screenshot({ path: out.replace(/\.mp4$/, '') + '.jpg', type: 'jpeg', quality: 85 });
  await browser.close();
  if (errors.length) { console.error(errors.join('\n')); process.exit(1); }
  if (code !== 0) process.exit(code);

  if (voice) {
    const muxed = out.replace(/\.mp4$/, '') + '.ovozli.mp4';
    ffmpegSync(['-i', out, '-i', voice, '-map', '0:v', '-map', '1:a', '-c', 'copy', '-shortest',
      '-metadata:s:a:0', `language=${lang === 'uz' ? 'uzb' : 'rus'}`, '-movflags', '+faststart', muxed]);
    fs.renameSync(muxed, out);
  }
  fs.rmSync(tmp, { recursive: true, force: true });
  console.log(`tayyor: ${out}`);
})();
