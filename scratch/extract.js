const fs = require('fs');
const md = fs.readFileSync('C:/Users/ASUS/Downloads/ELMS_Full_Design_Overhaul_Blue.md', 'utf8');

const headerHtmlMatch = /### HTML Structure\s*```html\s*([\s\S]*?)\s*```/.exec(md);
const headerJsMatch = /### Header JavaScript.*?\s*```javascript\s*([\s\S]*?)\s*```/.exec(md);

fs.writeFileSync('scratch/header_extract.html', headerHtmlMatch[1] + '\n' + headerJsMatch[1]);
