-- AlterTable
ALTER TABLE "User" ADD COLUMN "avatarUrl" TEXT;
ALTER TABLE "User" ADD COLUMN "bio" TEXT;

-- CreateTable
CREATE TABLE "SiteSettings" (
    "id" TEXT NOT NULL PRIMARY KEY DEFAULT 'singleton',
    "background" TEXT NOT NULL DEFAULT '#efe4cf',
    "foreground" TEXT NOT NULL DEFAULT '#4a4326',
    "surface" TEXT NOT NULL DEFAULT '#f8f3e6',
    "border" TEXT NOT NULL DEFAULT '#e2d3ac',
    "primary" TEXT NOT NULL DEFAULT '#7d7a4a',
    "primaryLight" TEXT NOT NULL DEFAULT '#b3af86',
    "accent" TEXT NOT NULL DEFAULT '#c9848a',
    "gold" TEXT NOT NULL DEFAULT '#c2a05e',
    "muted" TEXT NOT NULL DEFAULT '#8a7f5c',
    "updatedAt" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "Page" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "slug" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "content" TEXT NOT NULL,
    "showInNav" BOOLEAN NOT NULL DEFAULT true,
    "sortOrder" INTEGER NOT NULL DEFAULT 0,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateIndex
CREATE UNIQUE INDEX "Page_slug_key" ON "Page"("slug");
