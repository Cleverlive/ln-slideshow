<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>LN News Slider</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 0;
      font-family: sans-serif;
      background: white;
      overflow: hidden;
    }
    .slider {
      display: flex;
      flex-direction: row;
      width: 100vw;
      height: 100vh;
    }
    .text-section {
      flex: 1;
      padding: 80px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .headline {
      font-size: 2.5rem;
      font-weight: bold;
      color: #004c97;
      margin-bottom: 40px;
    }
    .description {
      font-size: 1rem;
      line-height: 1.5;
      color: #222;
    }
    .image-section {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #ffffff;
    }
    .image-section img {
      max-width: 1000%;
      max-height: 1000%;
      object-fit: contain;
    }
  </style>
</head>
<body>
  <div class="slider">
    <div class="text-section">
      <div class="headline" id="headline">Lade...</div>
      <div class="description" id="description">Bitte warten...</div>
    </div>
    <div class="image-section">
      <img id="news-image" src="" alt="News Bild">
    </div>
  </div>

  <script>
    const feedUrl = 'https://api.allorigins.win/get?url=' + encodeURIComponent('https://www.ln-online.de/arc/outboundfeeds/rss/tags_slug/luebeck/');
    const headlineEl = document.getElementById("headline");
    const descriptionEl = document.getElementById("description");
    const imageEl = document.getElementById("news-image");

    let items = [];
    let currentIndex = 0;

    function stripHtml(html) {
      const div = document.createElement("div");
      div.innerHTML = html;
      return div.textContent || div.innerText || "";
    }

    function showNews(index) {
      const item = items[index];
      headlineEl.textContent = item.title;
      descriptionEl.textContent = item.description;
      imageEl.src = item.image;
    }

    async function fetchFeed() {
      try {
        const response = await fetch(feedUrl);
        const result = await response.json();
        const parser = new DOMParser();
        const xml = parser.parseFromString(result.contents, "application/xml");
        const entries = xml.querySelectorAll("item");
        items = [];
        for (let i = 0; i < Math.min(entries.length, 5); i++) {
          const entry = entries[i];
          const title = entry.querySelector("title")?.textContent || "";
          const description = entry.querySelector("description")?.textContent || "";
          const media = entry.querySelector("media\\:content") || entry.getElementsByTagName("media:content")[0];
          let image = "";
          if (media && media.getAttribute("url")) {
            image = media.getAttribute("url");
          }
          items.push({
            title: title,
            description: stripHtml(description),
            image: image
          });
        }
        if (items.length > 0) {
          showNews(0);
          setInterval(() => {
            currentIndex = (currentIndex + 1) % items.length;
            showNews(currentIndex);
          }, 10000);
        } else {
          headlineEl.textContent = "Keine News gefunden.";
        }
      } catch (err) {
        headlineEl.textContent = "Fehler beim Laden.";
        console.error("RSS Fehler:", err);
      }
    }

    fetchFeed();
  </script>
</body>
</html>
