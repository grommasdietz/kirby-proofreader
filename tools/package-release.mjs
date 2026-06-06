import { execFileSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

const rawVersion = process.argv[2] ?? "";
const version = rawVersion.replace(/^v/, "");
const semverPattern = /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/;

if (semverPattern.test(version) !== true) {
  console.error("Usage: node tools/package-release.mjs <semver>");
  process.exit(1);
}

const root = process.cwd();
const packageName = path.basename(root);
const dist = path.join(root, "dist");
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), `${packageName}-release-`));
const packageRoot = path.join(tmp, packageName);
const tarPath = path.join(tmp, "archive.tar");
const zipPath = path.join(dist, `${packageName}.zip`);

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, "utf8"));
}

function writeJson(file, data) {
  fs.writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`, "utf8");
}

function withPackageVersion(data) {
  const result = {};
  let inserted = false;

  for (const [key, value] of Object.entries(data)) {
    if (key === "version") {
      continue;
    }

    result[key] = value;

    if (key === "description") {
      result.version = version;
      inserted = true;
    }
  }

  if (inserted !== true) {
    result.version = version;
  }

  return result;
}

try {
  fs.rmSync(dist, { recursive: true, force: true });
  fs.mkdirSync(dist, { recursive: true });
  fs.mkdirSync(packageRoot, { recursive: true });

  execFileSync(
    "git",
    ["archive", "--format=tar", "--worktree-attributes", "HEAD", "-o", tarPath],
    { cwd: root, stdio: "inherit" },
  );
  execFileSync("tar", ["-xf", tarPath, "-C", packageRoot], { stdio: "inherit" });

  const changelogPath = path.join(root, "CHANGELOG.md");
  if (fs.existsSync(changelogPath)) {
    fs.copyFileSync(changelogPath, path.join(packageRoot, "CHANGELOG.md"));
  }

  const composerPath = path.join(packageRoot, "composer.json");
  writeJson(composerPath, withPackageVersion(readJson(composerPath)));

  execFileSync("zip", ["-qr", zipPath, packageName], { cwd: tmp, stdio: "inherit" });
  console.log(`Created ${path.relative(root, zipPath)} for ${version}`);
} finally {
  fs.rmSync(tmp, { recursive: true, force: true });
}
