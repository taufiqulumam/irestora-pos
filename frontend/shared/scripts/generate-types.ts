import { writeFileSync, mkdirSync, existsSync } from 'fs';
import { resolve, dirname } from 'path';
import { generateTypes } from 'openapi-typescript';

async function main() {
  const openApiPath = resolve(__dirname, '../../../backend/storage/api-docs/api-docs.json');
  const outputPath = resolve(__dirname, '../types/api.d.ts');

  if (!existsSync(openApiPath)) {
    console.error(`OpenAPI spec not found at: ${openApiPath}`);
    console.error('Run "php artisan l5-swagger:generate" in backend first');
    process.exit(1);
  }

  const outputDir = dirname(outputPath);
  if (!existsSync(outputDir)) {
    mkdirSync(outputDir, { recursive: true });
  }

  console.log(`Generating types from ${openApiPath}...`);

  const types = await generateTypes(openApiPath, {
    exportType: true,
    enum: 'const',
    unknownAny: true,
    defaultNonNullable: true,
    unionEnums: true,
  });

  writeFileSync(outputPath, types);
  console.log(`Types written to ${outputPath}`);
}

main().catch((err) => {
  console.error('Error generating types:', err);
  process.exit(1);
});