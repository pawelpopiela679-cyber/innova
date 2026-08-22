-- CreateTable
CREATE TABLE "PricingTier" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "classTypeId" TEXT NOT NULL,
    "label" TEXT NOT NULL,
    "ageLabel" TEXT NOT NULL,
    "durationMin" INTEGER NOT NULL,
    "priceMonthly" INTEGER NOT NULL,
    "oneTimeFee" INTEGER,
    "sortOrder" INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT "PricingTier_classTypeId_fkey" FOREIGN KEY ("classTypeId") REFERENCES "ClassType" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

-- CreateIndex
CREATE INDEX "PricingTier_classTypeId_idx" ON "PricingTier"("classTypeId");
