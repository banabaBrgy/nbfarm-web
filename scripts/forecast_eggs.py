"""
forecast_eggs.py

Simple Linear Regression forecast para sa egg production.
Kinukuha ang daily totals mula sa `production` table, nagt-train ng
Linear Regression (day index -> total eggs), tapos nag-p-predict ng
susunod na N days.

Output: JSON lang sa stdout, walang ibang print, para safe i-parse
ng PHP (shell_exec + json_decode).

Usage:
    python forecast_eggs.py --host=127.0.0.1 --database=nbpoultry \
        --user=root --password=secret --start-date=2026-07-14 --end-date=2026-07-20

Requirements (i-install muna):
    pip install pandas scikit-learn pymysql
"""

import argparse
import json
import sys
from datetime import timedelta

import pandas as pd
import pymysql
from sklearn.linear_model import LinearRegression


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument('--host', required=True)
    parser.add_argument('--database', required=True)
    parser.add_argument('--user', required=True)
    parser.add_argument('--password', required=True)
    parser.add_argument('--port', type=int, default=3306)
    parser.add_argument('--start-date', required=True, help='YYYY-MM-DD, unang araw ng target range')
    parser.add_argument('--end-date', required=True, help='YYYY-MM-DD, huling araw ng target range (inclusive)')
    return parser.parse_args()


def fetch_daily_totals(args):
    conn = pymysql.connect(
        host=args.host,
        user=args.user,
        password=args.password,
        database=args.database,
        port=args.port,
        cursorclass=pymysql.cursors.DictCursor,
    )
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT DATE(production_date) AS production_date,
                       SUM(eggs_produced) AS total
                FROM production
                GROUP BY DATE(production_date)
                ORDER BY production_date
            """)
            rows = cursor.fetchall()
    finally:
        conn.close()

    return pd.DataFrame(rows)


def main():
    args = parse_args()

    df = fetch_daily_totals(args)

    if df.empty or len(df) < 2:
        # Hindi pa sapat ang datos para mag-forecast nang maayos
        print(json.dumps({'labels': [], 'dates': [], 'data': []}))
        return

    df['production_date'] = pd.to_datetime(df['production_date'])
    df = df.sort_values('production_date').reset_index(drop=True)

    first_date = df['production_date'].iloc[0]

    # day_index = bilang ng araw mula sa unang record (base sa calendar,
    # hindi sa row position), para tama ang slope kahit may mga
    # araw na walang laman sa DB (gaps)
    df['day_index'] = (df['production_date'] - first_date).dt.days
    X = df[['day_index']]
    y = df['total']

    model = LinearRegression()
    model.fit(X, y)

    last_date = df['production_date'].iloc[-1]

    start_date = pd.to_datetime(args.start_date)
    end_date = pd.to_datetime(args.end_date)

    # Buong range mula start hanggang end (kasama pareho), para makita
    # rin ang forecast/predicted line sa mga araw na nakalipas na
    # (hindi lang sa mga susunod pa)
    forecast_dates = pd.date_range(start=start_date, end=end_date, freq='D')
    forecast_indices = [(d - first_date).days for d in forecast_dates]

    predictions = model.predict(pd.DataFrame({'day_index': forecast_indices}))

    # I-clip sa 0 pataas lang (walang negative na eggs) at i-round
    predictions = [max(0, round(float(p))) for p in predictions]

    result = {
        'labels': [d.strftime('%a') for d in forecast_dates],   # e.g. "Mon", "Tue"
        'dates': [d.strftime('%Y-%m-%d') for d in forecast_dates],
        'data': predictions,
    }

    print(json.dumps(result))


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        # Kahit magka-error, JSON pa rin ang ilabas para hindi masira
        # ang json_decode sa PHP side
        print(json.dumps({'labels': [], 'dates': [], 'data': [], 'error': str(e)}), file=sys.stdout)
        sys.exit(0)