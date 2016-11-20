<?php

class Metrodb_BlobStream {

	protected $table  = '';
	protected $col    = '';
	protected $id     = '';

	/**
	 * Prepare to stream a blob record
	 *
	 * @param string $table SQL table name
	 * @param string $col   SQL column name
	 * @param int    $id    Unique record id
	 * @param int    $pct   Size of each blob chunk as percentage of total
	 * @param string $idcol Name of column that holds identity if not table.'_id'
	 * @return array stream handle with info needed for nextChunk()
	 */
	public function __construct($connector, $table, $blobCol, $id, $pct=10, $idcol='') {
		$qc = $connector->qc;
		if ($idcol == '') {$idcol = $table.'_id';}
		$rows = $connector->queryGetAll('SELECT CHAR_LENGTH('.$qc.$blobCol.$qc.') as charlen from '.$qc.$table.$qc.' WHERE '.$qc.$idcol.$qc.' = '.$id);
		$record = $rows[0];
		$this->table        = $qc.$table.$qc;
		$this->col          = $blobCol;
		$this->id           = $id;
		$this->pct          = $pct;
		$this->idcol        = $idcol;
		$this->charlen      = $record['charlen'];
		$this->finished     = FALSE;
		$this->chareach     = floor($this->charlen * ($pct / 100));
		$this->charlast     = $this->charlen % ((1/$pct) * 100);
		$this->pctdone      = 0;
	}

	/**
	 * Select a percentage of a blob field
	 *
	 * @param $connector Metrodb_Connector subclass
	 */
	public function read($connector) {
		if ($this->finished) { return NULL; }
		$qc = $connector->qc;

		$_x = (floor($this->pctdone/$this->pct) * $this->chareach) + 1;
		$_s = $this->chareach;

		if ($this->pctdone + $this->pct >= 100) {
			//grab the uneven bits with this last pull
			$_s += $this->charlast;
			$rows = $connector->queryGetAll('SELECT SUBSTR('.$qc.$this->col.$qc.','.$_x.') 
				AS '.$qc.'blobstream'.$qc.' FROM '.$this->table.' WHERE '.$qc.$this->idcol.$qc.' = '.sprintf('%d',$this->id));
		} else {
			$rows = $connector->queryGetAll('SELECT SUBSTR('.$qc.$this->col.$qc.','.$_x.','.$_s.') 
				AS '.$qc.'blobstream'.$qc.' FROM '.$this->table.' WHERE '.$qc.$this->idcol.$qc.' = '.sprintf('%d',$this->id));
		}
		$this->pctdone += $this->pct;
		if ($this->pctdone >= 100) { 
			$this->finished = TRUE;
		}
		return $rows[0]['blobstream'];
	}
}
