const fs = require('fs');
const content = fs.readFileSync('dragon_quest_ultimate.html', 'utf8');
const match = content.match(/const rawMap = \[\s*([\s\S]*?)\s*\];/);
if (match) {
  const lines = match[1].split('\n').map(l => {
    const m = l.match(/"([^"]+)"/);
    return m ? m[1] : '';
  }).filter(l => l.length > 0);
  lines.forEach((l, i) => {
    if (l.length !== 100) {
      console.log(`Line ${i}: len ${l.length}`);
    }
  });
  console.log('Total valid 100-char lines:', lines.filter(l => l.length === 100).length);
}
