const { Client, LocalAuth } = require("whatsapp-web.js");
const qrcode = require("qrcode-terminal");
const { execFile } = require("child_process");
const path = require("path");

// ── Config ────────────────────────────────────────────────────────────────
const PYTHON_PATH = "python";
const PROCESS_SCRIPT = path.join(__dirname, "..", "python", "process.py");
const TRIGGER_PREFIX = "~";
const lastTransaction = {};

// ── Init WhatsApp Client ──────────────────────────────────────────────────
const client = new Client({
  authStrategy: new LocalAuth({ clientId: "ai-finance" }),
  puppeteer: {
    headless: true,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  },
});

// ── QR Code ───────────────────────────────────────────────────────────────
client.on("qr", (qr) => {
  console.log("\n📱 Scan QR code ini dengan WhatsApp kamu:\n");
  qrcode.generate(qr, { small: true });
});

// ── Ready ─────────────────────────────────────────────────────────────────
client.on("ready", () => {
  console.log("\n✅ WhatsApp Bot aktif!");
  console.log("💬 Kirim pesan ke nomormu sendiri dengan awalan ~");
  console.log("   Contoh: ~beli pentol 20k");
  console.log("   Contoh: ~gajian 3jt");
  console.log("   Contoh: ~saldo\n");
});

client.on("message_create", async (msg) => {
  const body = msg.body.trim();
  if (!body.startsWith(TRIGGER_PREFIX)) return;

  const command = body.slice(TRIGGER_PREFIX.length).trim();
  const userId = msg.from;
  console.log(`📩 Diterima: "${command}"`);

  // ── Handle ~salah [kategori] ─────────────────────────────────────────
  if (command.toLowerCase().startsWith("salah")) {
    const parts = command.split(" ");
    const correctCategory = parts[1]?.toLowerCase();
    const last = lastTransaction[userId];

    if (!last) {
      msg.reply("⚠️ Tidak ada transaksi sebelumnya yang bisa dikoreksi.");
      return;
    }
    if (!correctCategory) {
      msg.reply(
        "⚠️ Tulis kategori yang benar.\n" +
          "Contoh: *~salah makan*\n" +
          "Pilihan: makan, minum, transport, hiburan, income, lainnya",
      );
      return;
    }

    const LEARN_SCRIPT = path.join(__dirname, "..", "python", "learn.py");
    execFile(
      PYTHON_PATH,
      [LEARN_SCRIPT, last.description, correctCategory],
      { encoding: "utf8", env: { ...process.env, PYTHONIOENCODING: "utf-8" } },
      (error, stdout, stderr) => {
        if (error) {
          console.error("❌ Learn error:", stderr);
          msg.reply("❌ Gagal menyimpan koreksi.");
          return;
        }
        execFile(
          PYTHON_PATH,
          [path.join(__dirname, "..", "python", "reset_cache.py")],
          { env: { ...process.env, PYTHONIOENCODING: "utf-8" } },
          () => {},
        );
        msg.reply(stdout.trim());
        lastTransaction[userId] = null;
      },
    );
    return;
  }

  // ── Handle ~dataset ──────────────────────────────────────────────────
  if (command.toLowerCase() === "dataset") {
    const LEARN_SCRIPT = path.join(__dirname, "..", "python", "learn.py");
    execFile(
      PYTHON_PATH,
      [LEARN_SCRIPT, "--stats", ""],
      { encoding: "utf8", env: { ...process.env, PYTHONIOENCODING: "utf-8" } },
      (error, stdout) => {
        msg.reply(stdout.trim() || "📭 Belum ada koreksi.");
      },
    );
    return;
  }

  // ── Handle transaksi biasa ───────────────────────────────────────────
  const PROCESS_SCRIPT = path.join(__dirname, "..", "python", "process.py");
  execFile(
    PYTHON_PATH,
    [PROCESS_SCRIPT, command],
    { encoding: "utf8", env: { ...process.env, PYTHONIOENCODING: "utf-8" } },
    (error, stdout, stderr) => {
      if (error) {
        console.error("❌ Python error:", stderr);
        msg.reply("❌ Terjadi error. Cek terminal.");
        return;
      }
      const response = stdout.trim();

      lastTransaction[userId] = { description: command };

      console.log(`📤 Balas: "${response}"`);
      msg.reply(response);
    },
  );
});

// ── Auth failure ──────────────────────────────────────────────────────────
client.on("auth_failure", () => {
  console.error("❌ Auth gagal. Hapus folder .wwebjs_auth lalu restart.");
});

client.on("disconnected", (reason) => {
  console.log("⚠️ Disconnected:", reason);
});

// ── Start ─────────────────────────────────────────────────────────────────
console.log("🚀 Memulai AI Finance Tracker WhatsApp Bot...");
client.initialize();
