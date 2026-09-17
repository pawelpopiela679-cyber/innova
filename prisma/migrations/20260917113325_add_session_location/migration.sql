-- RedefineTables
PRAGMA defer_foreign_keys=ON;
PRAGMA foreign_keys=OFF;
CREATE TABLE "new_ClassSession" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "classTypeId" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "description" TEXT,
    "startsAt" DATETIME NOT NULL,
    "endsAt" DATETIME NOT NULL,
    "capacity" INTEGER NOT NULL DEFAULT 10,
    "location" TEXT NOT NULL DEFAULT 'Pracownia',
    "meetingUrl" TEXT,
    "instructorId" TEXT,
    "instructorName" TEXT NOT NULL,
    "status" TEXT NOT NULL DEFAULT 'SCHEDULED',
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "ClassSession_classTypeId_fkey" FOREIGN KEY ("classTypeId") REFERENCES "ClassType" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "ClassSession_instructorId_fkey" FOREIGN KEY ("instructorId") REFERENCES "User" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);
INSERT INTO "new_ClassSession" ("capacity", "classTypeId", "createdAt", "description", "endsAt", "id", "instructorId", "instructorName", "meetingUrl", "startsAt", "status", "title") SELECT "capacity", "classTypeId", "createdAt", "description", "endsAt", "id", "instructorId", "instructorName", "meetingUrl", "startsAt", "status", "title" FROM "ClassSession";
DROP TABLE "ClassSession";
ALTER TABLE "new_ClassSession" RENAME TO "ClassSession";
CREATE INDEX "ClassSession_startsAt_idx" ON "ClassSession"("startsAt");
CREATE INDEX "ClassSession_classTypeId_idx" ON "ClassSession"("classTypeId");
PRAGMA foreign_keys=ON;
PRAGMA defer_foreign_keys=OFF;
