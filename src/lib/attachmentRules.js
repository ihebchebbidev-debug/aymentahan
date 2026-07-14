const AUDIO_EXTENSIONS = ["aac", "mp3", "wav", "ogg", "oga", "m4a", "mp4", "m4b", "flac", "webm"];

const EXTENSION_TO_MIME = {
  aac: "audio/aac",
  mp3: "audio/mpeg",
  wav: "audio/wav",
  ogg: "audio/ogg",
  oga: "audio/ogg",
  m4a: "audio/mp4",
  mp4: "audio/mp4",
  m4b: "audio/mp4",
  flac: "audio/flac",
  webm: "audio/webm",
  pdf: "application/pdf",
  png: "image/png",
  jpg: "image/jpeg",
  jpeg: "image/jpeg",
  webp: "image/webp",
  gif: "image/gif",
  bmp: "image/bmp",
};

const SUPPORTED_ATTACHMENT_MIME_TYPES = [
  "application/pdf",
  "image/png",
  "image/jpeg",
  "image/jpg",
  "image/webp",
  "image/gif",
  "image/bmp",
  "audio/mpeg",
  "audio/mp3",
  "audio/aac",
  "audio/x-aac",
  "audio/wav",
  "audio/x-wav",
  "audio/ogg",
  "audio/oga",
  "audio/mp4",
  "audio/x-m4a",
  "audio/m4a",
  "audio/flac",
  "audio/webm",
  "application/x-aac",
  "application/x-mpeg",
  "application/x-mp3",
  "application/ogg",
  "application/x-m4a",
  "application/x-wav",
];

function extOf(name) {
  const match = /\.([^.]+)$/.exec(name || "");
  return match ? match[1].toLowerCase() : "";
}

export function isSupportedAttachmentFile(file) {
  const mime = (file?.type || "").toLowerCase();
  const ext = extOf(file?.name || "");
  const extMime = EXTENSION_TO_MIME[ext];

  if (mime.startsWith("image/")) return true;
  if (mime === "application/pdf") return true;
  if (mime.startsWith("audio/")) return true;
  if (extMime) return SUPPORTED_ATTACHMENT_MIME_TYPES.includes(extMime) || SUPPORTED_ATTACHMENT_MIME_TYPES.includes(mime);
  if (!mime || mime === "application/octet-stream") {
    return ["pdf", ...AUDIO_EXTENSIONS].includes(ext) || ["png", "jpg", "jpeg", "webp", "gif", "bmp"].includes(ext);
  }

  return SUPPORTED_ATTACHMENT_MIME_TYPES.includes(mime);
}

export function isAudioAttachmentFile(file) {
  const mime = (file?.type || "").toLowerCase();
  const ext = extOf(file?.name || "");
  const extMime = EXTENSION_TO_MIME[ext];
  return mime.startsWith("audio/") || extMime?.startsWith("audio/") || AUDIO_EXTENSIONS.includes(ext);
}

export function attachmentAcceptAttribute() {
  return "application/pdf,image/*,audio/*,.aac,.mp3,.wav,.ogg,.oga,.m4a,.mp4,.m4b,.flac,.webm";
}
