DELETE FROM node__field_language_taxo_vern
WHERE field_language_taxo_vern_target_id = 393
AND bundle = 'common_names'
AND entity_id IN (
  SELECT entity_id FROM (
    SELECT entity_id FROM node__field_language_taxo_vern
    WHERE field_language_taxo_vern_target_id = 2277
    AND bundle = 'common_names'
  ) AS sub
);
