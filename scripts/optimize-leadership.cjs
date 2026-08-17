const sharp = require("../node_modules/.pnpm/sharp@0.34.5/node_modules/sharp");

const directory = "public/images/leadership/";
const portraits = [
  ["darren-crombie.jpg", "darren-crombie.webp"],
  ["darren-crombie-ai.png", "darren-crombie-ai.webp"],
  ["cankat-sarac.jpeg", "cankat-sarac.webp"],
  ["cankat-sarac-ai.png", "cankat-sarac-ai.webp"],
  ["kateule-bwalya.png", "kateule-bwalya.webp"],
  ["kateule-bwalya-ai.png", "kateule-bwalya-ai.webp"],
  ["laura-allbuary.jpg", "laura-allbuary.webp"],
  ["laura-allbuary-ai.png", "laura-allbuary-ai.webp"],
  ["jack-ford.jpg", "jack-ford.webp"],
  ["jack-ford-ai.png", "jack-ford-ai.webp"]
];

Promise.all(
  portraits.map(([source, output]) =>
    sharp(directory + source)
      .resize(1000, 1000, { fit: "cover", position: "attention" })
      .webp({ quality: 86, effort: 5 })
      .toFile(directory + output)
  )
).then(() => console.log("Optimised 10 leadership portraits."));
