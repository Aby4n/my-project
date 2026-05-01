const fs = require('fs');
const content = fs.readFileSync('dragon_quest_ultimate.html', 'utf8');
const match = content.match(/const rawMap = \[\s*([\s\S]*?)\s*\];/);
if (match) {
  const lines = match[1].split('\n').map(l => {
    const m = l.match(/"([^"]+)"/);
    return m ? m[1] : '';
  }).filter(l => l.length > 0);
  
  lines.forEach((l, y) => {
    for (let x = 0; x < l.length; x++) {
      const c = l[x];
      if (c !== 'a' && c !== 'b' && c !== 'c' && isNaN(Number(c)) && c !== '\r') {
        console.log(`Invalid char at y=${y}, x=${x}: '${c}'`);
      }
    }
  });
  console.log('Done checking characters.');
}
if (match) {
  const
}